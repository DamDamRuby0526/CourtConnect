<?php

require_once __DIR__ . "/email_config.php";

function sendOtpEmail(
    string $recipientEmail,
    string $recipientName,
    string $otp
): array {
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, "UTF-8");
    $safeName = htmlspecialchars(
        $recipientName !== "" ? $recipientName : "CourtConnect user",
        ENT_QUOTES,
        "UTF-8"
    );

    $payload = [
        "sender" => [
            "name" => COURTCONNECT_SENDER_NAME,
            "email" => COURTCONNECT_SENDER_EMAIL
        ],
        "to" => [
            [
                "email" => $recipientEmail,
                "name" => $recipientName
            ]
        ],
        "subject" => "Your CourtConnect verification code",
        "htmlContent" =>
            "<!DOCTYPE html>
            <html>
            <body style=\"font-family:Arial,sans-serif;background:#f7f4ea;padding:24px;\">
                <div style=\"max-width:520px;margin:auto;background:#ffffff;
                            padding:32px;border-radius:14px;\">
                    <h2 style=\"color:#0B345C;margin-top:0;\">
                        Verify your CourtConnect account
                    </h2>

                    <p>Hello {$safeName},</p>

                    <p>Use this verification code to complete your registration:</p>

                    <div style=\"font-size:32px;font-weight:bold;
                                letter-spacing:8px;color:#78B936;
                                margin:24px 0;\">
                        {$safeOtp}
                    </div>

                    <p>This code expires in 5 minutes.</p>

                    <p style=\"color:#666666;font-size:13px;\">
                        Do not share this code. If you did not request it,
                        you can ignore this email.
                    </p>
                </div>
            </body>
            </html>",
        "textContent" =>
            "Hello {$recipientName},\n\n" .
            "Your CourtConnect verification code is {$otp}.\n" .
            "It expires in 5 minutes. Do not share this code."
    ];

    $curl = curl_init(BREVO_API_URL);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "api-key: " . BREVO_API_KEY,
            "content-type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($responseBody === false || $curlError !== "") {
        error_log("Brevo connection error: " . $curlError);

        return [
            "success" => false,
            "message" => "Unable to connect to the email service."
        ];
    }

    $responseData = json_decode($responseBody, true);

    if (
        $httpCode >= 200 &&
        $httpCode < 300 &&
        isset($responseData["messageId"])
    ) {
        return [
            "success" => true,
            "message" => "Verification code sent to your email."
        ];
    }

    error_log(
        "Brevo API error: HTTP {$httpCode} - {$responseBody}"
    );

    return [
        "success" => false,
        "message" => "Unable to send the verification email."
    ];
}

?>