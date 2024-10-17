<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initiate Payment</title>
</head>
<body>
<h1>Initiate Payment</h1>
<form action="{{ route('payment.initiate') }}" method="POST">
    @csrf
    <label for="amount">Amount:</label>
    <input type="text" id="amount" name="amount" required>
    <button type="submit">Initiate Payment</button>
</form>
</body>
</html>
