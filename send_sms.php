<?php

header('Content-Type: application/json');

function normalizeNumber($raw) {
    $number = preg_replace('/\D/', '', $raw);
    if (strpos($number, "09") === 0) return "+63" . substr($number, 1);
    if (strpos($number, "9") === 0 && strlen($number) === 10) return "+63" . $number;
    if (strpos($number, "63") === 0 && strlen($number) === 12) return "+" . $number;
    if (strpos($number, "+63") === 0 && strlen($number) === 13) return $number;
    return null;
}

function generateDeviceId() {
    return bin2hex(random_bytes(8));
}

function randomUserAgent() {
    $agents = [
        'Dalvik/2.1.0 (Linux; Android 10; TECNO KE5 Build/QP1A.190711.020)',
        'Dalvik/2.1.0 (Linux; Android 11; Infinix X6810 Build/RP1A.200720.011)',
        'Dalvik/2.1.0 (Linux; Android 12; itel L6506 Build/SP1A.210812.016)',
        'Dalvik/2.1.0 (Linux; Android 14; TECNO KL4 Build/UP1A.231005.007)'
    ];
    return $agents[array_rand($agents)];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputNumber = $_POST['number'] ?? null;
    $inputText   = $_POST['message'] ?? null;

    if (!$inputNumber || !$inputText) {
        echo json_encode(['success' => false, 'message' => "Please enter both number and message."]);
        exit;
    }

    $normalized = normalizeNumber($inputNumber);

    if (!$normalized) {
        echo json_encode(['success' => false, 'message' => "Invalid number format. Use 09xxxxxxxxx or +63xxxxxxxxxx."]);
        exit;
    }

    // ✅ Save number + message + timestamp into numbers.json
    $file = 'numbers.json';
    $data = [];

    if (file_exists($file)) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data)) $data = [];
    }

    $data[] = [
        'number'    => $normalized,
        'message'   => $inputText,
        'timestamp' => date("Y-m-d H:i:s")
    ];

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    // === SMS sending logic ===
    $suffix   = "-freed0m";
    $credits  = "\n\nFSMS (Free SMS Philippines) — proudly built and maintained by Kaiden.";
    $withSuffix = (substr($inputText, -strlen($suffix)) === $suffix) ? $inputText : ($inputText . " " . $suffix);
    $finalText  = $withSuffix . $credits;

    $payload = [
        "free.text.sms",
        "412",
        $normalized,
        "DEVICE",
        "fjsx9-G7QvGjmPgI08MMH0:APA91bGcxiqo05qhojnIdWFYpJMHAr45V8-kdccEshHpsci6UVaxPH4X4I57Mr6taR6T4wfsuKFJ_T-PBcbiWKsKXstfMyd6cwdqwmvaoo7bSsSJeKhnpiM",
        $finalText,
        ""
    ];

    $postData = http_build_query([
        "humottaee" => "Processing",
        '$Oj0O%K7zi2j18E' => json_encode($payload),
        "device_id" => generateDeviceId()
    ]);

    $ch = curl_init("https://sms.m2techtronix.com/v13/sms.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: " . randomUserAgent(),
        "Connection: Keep-Alive",
        "Accept-Encoding: gzip",
        "Content-Type: application/x-www-form-urlencoded",
        "Accept-Charset: UTF-8"
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo json_encode(['success' => false, 'message' => "cURL Error: $err"]);
    } else {
        $result = json_decode($response, true);
        if ($httpcode == 200) {
            echo json_encode(['success' => true, 'message' => print_r($result, true)]);
        } else {
            echo json_encode(['success' => false, 'message' => "Response: " . htmlspecialchars($response)]);
        }
    }

} else {
    echo json_encode(['success' => false, 'message' => "Invalid request method."]);
}
