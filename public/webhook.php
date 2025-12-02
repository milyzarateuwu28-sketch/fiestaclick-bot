<?php

http_response_code(200);

/* ========== VERIFICACIÓN PARA META (GET) ========== */
if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($token === "FiestaClick2025") {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        exit("TOKEN INVALIDO");
    }
}

/* ========== TOKEN PERMANENTE Y PHONE ID ========== */
$token = "TU_TOKEN_AQUI";  // ← reemplázalo
$phone_number_id = "TU_PHONE_ID_AQUI"; // ← reemplázalo

/* ========== LEER MENSAJE DE META (POST) ========== */
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["entry"][0]["changes"][0]["value"]["messages"])) {
    exit;
}

$msg = $data["entry"][0]["changes"][0]["value"]["messages"][0];
$telefono = preg_replace('/\D/', '', $msg["from"]);
$tipo = $msg["type"];

$mensaje = "";
if ($tipo === "text") {
    $mensaje = trim($msg["text"]["body"]);
}

/* ========== RESPUESTA PARA PRUEBAS ========== */
$respuesta = "Hola 👋, soy el bot de *FiestaClick*.\n";
$respuesta .= "Recibí tu mensaje: *$mensaje*\n";
$respuesta .= "Esto es solo una prueba, sin base de datos 😊";

/* ========== ENVIAR RESPUESTA ========== */
sendMessage($telefono, $respuesta, $token, $phone_number_id);

echo json_encode(["status" => "ok"]);

/* ========== FUNCIÓN PARA ENVIAR MENSAJES ========== */
function sendMessage($telefono, $texto, $token, $phone_number_id) {

    $url = "https://graph.facebook.com/v20.0/$phone_number_id/messages";

    $data = [
        "messaging_product" => "whatsapp",
        "to" => $telefono,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $texto
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
