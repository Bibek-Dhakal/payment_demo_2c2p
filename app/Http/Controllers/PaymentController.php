<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(Logger $logger)
    {
        parent::__construct($logger, env('SECRET_KEY', "ECC4E54DBA738857B84A7EBC6B5DC7187B8DA68750E88AB53AAA41F548D6F2D9"));
    }

    public function initiatePayment(Request $request): Application|JsonResponse|Redirector|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $amount = $request->input('amount');

        $merchantID = env('MERCHANT_ID', 'JT01');
        $currencyCode = env('CURRENCY_CODE', 'NPR');
        $invoiceNo = time();

        $url = env('PAYMENT_API_URL', 'https://sandbox-pgw.2c2p.com/payment/4.3/paymenttoken');
        $payload = [
            'merchantID' => $merchantID,
            'invoiceNo' => $invoiceNo,
            'description' => 'item 1',
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'paymentChannel' => ['CC'],
            'backendReturnUrl' => route('payment.callback'),
            'frontendReturnUrl' => route('payment.response')
        ];

        try {
            $responseData = $this->curl_payment_init($url, $payload);
        } catch (Exception $e) {
            $this->logger->error('Payment initiation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }

        // Check response and redirect
        if (isset($responseData['respCode']) && $responseData['respCode'] === '0000') {
            // On success, redirect to the payment page
            $webPaymentUrl = $responseData['webPaymentUrl'];
            return redirect($webPaymentUrl);
        } else {
            // Handle errors
            $this->logger->error('Payment initiation failed', ['response' => $responseData]);
            return response()->json(['error' => $responseData['respDesc']], 400);
        }
    }

    public function paymentCallback(Request $request): JsonResponse
    {
        // Receive the JSON payload from 2C2P's backend response
        $data = $request->json()->all();

        // Check the response code to confirm payment success
        if (isset($data['respCode']) && $data['respCode'] === '0000') {
            // Payment was successful; process the order
            $invoiceNo = $data['invoiceNo'];
            $amount = $data['amount'];
            $tranRef = $data['tranRef'];

            // order in db with invoice and token and so on

            return response()->json(['message' => 'Payment Successful']);
        }

        // Handle failed or pending payment
        $this->logger->error('Payment callback failed', ['response' => $data]);
        return response()->json(['error' => $data['respDesc']], 400);
    }

    public function paymentResponse(Request $request): JsonResponse
    {
        // Check the response code from 2C2P and display appropriate message
        $respCode = $request->query('respCode');
        $respDesc = $request->query('respDesc');

        if ($respCode === '2000') {
            return response()->json(['message' => 'Payment completed successfully!']);
        }
        return response()->json(['error' => 'Payment failed or pending: ' . htmlspecialchars($respDesc)]);
    }

//    private function generateRandomString(): string
//    {
//        try {
//            return random_bytes(32);
//        } catch (Exception $e) {
//            $this->logger->error('Random bytes generation failed', ['error' => $e->getMessage()]);
//            return response()->json(['error' => 'Random bytes generation failed'], 500);
//        }
//    }
}
