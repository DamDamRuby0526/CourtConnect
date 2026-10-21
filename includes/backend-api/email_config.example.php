<?php

// Copy this file to email_config.local.php, add your Brevo API key, set
// sender_email to an address verified in Brevo, and set base_url to the
// public CourtConnect URL (HTTPS in production).
return [
    "api_key" => "",
    "sender_email" => "your-verified-sender@example.com",
    "sender_name" => "CourtConnect",
    "base_url" => "https://your-courtconnect-domain.example"
];
