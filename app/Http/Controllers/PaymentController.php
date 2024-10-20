<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Logger;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(Logger $logger)
    {
        parent::__construct($logger, env('SECRET_KEY', "ECC4E54DBA738857B84A7EBC6B5DC7187B8DA68750E88AB53AAA41F548D6F2D9"));
    }

    public function initiatePayment(Request $request): Application|Response|JsonResponse|Redirector|RedirectResponse|ResponseFactory
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $amount = $request->input('amount');
//        $merchantID = env('MERCHANT_ID', 'JT01');
        $currencyCode = env('CURRENCY_CODE', 'NPR');
        $invoiceNo = time();
        $url = 'https://core.demo-paco.2c2p.com/api/2.0/Payment/prePaymentUI';

        $payload = [
            'officeId' => 'aaa', // PUT YOUR OFFICE ID
            'terminalID' => 'your_terminal_id',  // PUT YOUR TERMINAL ID
            'orderNo' => $invoiceNo,
            'productDescription' => 'item 1',
            'paymentCategory' => 'ECOM',
            'paymentType' => 'CC-VI',
            'preferredPaymentTypes' => ['CC-VI'],
            'paymentExpiryDateTime' => now()->addMinutes(30)->toIso8601String(),
            'mcpFlag' => 'Y',
            'preferredMcpType' => 'DCC',
            'request3dsFlag' => 'N',
            'transactionAmount' => [
                'amount' => $amount,
                'currencyCode' => $currencyCode,
            ],
            'notificationURLs' => [
                'confirmationURL' => route('payment.callback'),
                'failedURL' => route('payment.response'),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secret,  // PUT API KEY
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($url, json_encode($payload));

            $responseData = $response->json();

            if (isset($responseData['apiResponse']['responseCode']) && $responseData['apiResponse']['responseCode'] === 'PC-B050000') {
                $paymentPageURL = $responseData['data']['paymentPage']['paymentPageURL'] ?? null;

                if ($paymentPageURL) {
                    if (env('APP_ENV') === 'production') {
                        return redirect($paymentPageURL);
                    }

                    return response($paymentPageURL);
                } else {
                    $this->logger->error('Payment initiation failed: Payment page URL not found', ['response' => $responseData]);
                    return response()->json(['error' => 'Payment page URL not found'], 400);
                }
            } else {
                $this->logger->error('Payment initiation failed', ['response' => $responseData]);
                return response()->json(['error' => $responseData['title'] ?? 'Unknown error'], 400);
            }
        } catch (Exception $e) {
            $this->logger->error('Payment initiation failed', ['error' => $e->getMessage()]);
            if (env('APP_ENV') === 'production') {
                return redirect()->back()->with('error', 'Payment initiation failed');
            }
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function paymentCallback(Request $request): JsonResponse
    {
        $data = $request->json()->all();

        if (isset($data['respCode']) && $data['respCode'] === '0000') {
            $invoiceNo = $data['invoiceNo'];
            $amount = $data['amount'];
            $tranRef = $data['tranRef'];

            // Process the order in the database

            return response()->json(['message' => 'Payment Successful']);
        }

        $this->logger->error('Payment callback failed', ['response' => $data]);
        return response()->json(['error' => $data['respDesc']], 400);
    }

    public function paymentResponse(Request $request): JsonResponse
    {
        $respCode = $request->query('respCode');
        $respDesc = $request->query('respDesc');

        if ($respCode === '2000') {
            return response()->json(['message' => 'Payment completed successfully!']);
        }
        return response()->json(['error' => 'Payment failed or pending: ' . htmlspecialchars($respDesc)]);
    }
}
