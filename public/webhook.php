<?php
include "db.php";
$conexion = db_connect();

http_response_code(200); // META siempre necesita 200 OK

/* ====== VERIFICACIÓN DE WEBHOOK (GET) ====== */
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

/* ====== TOKEN PERMANENTE ====== */
$token = "EAAKNJaroGzwBQDBDZBOC71ycZApcJ0blc9xDjhKszH1XQZCOKZC6o5vlzsuF12KbERZAy4i42ZCWK5aDIMagkaZBcXQqXR0s8aqFdyjyUFg20kapPV5BuZBgdVQ50dj91ml7Q8qOUNlRcleypCkZAbFZB7QvsYYLlLA9HtToL1EfVFACFdSMmcDKdcQHfxPGQEZARZADIVRSs5XyH462JevpPLtg8LHZAJzsZCRIZA19mrWHXBdeI4XkngnMHVZBtdDAR1VMwJcaKToP2gOHi3ZCy5qwgFjFmcmxf"; 
$phone_number_id = "901673896360508";
$waba_id = "765696465929924";

/* ====== LECTURA DEL JSON ====== */
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["entry"][0]["changes"][0]["value"]["messages"])) {
    exit;
}

$msg = $data["entry"][0]["changes"][0]["value"]["messages"][0];

$telefono = preg_replace('/\D/', '', $msg["from"]); // solo números
$tipo = $msg["type"];

$mensaje = "";
$imagenURL = null;

/* ====== MENSAJE DE TEXTO ====== */
if ($tipo === "text") {
    $mensaje = trim($msg["text"]["body"]);
}

/* ====== MENSAJE DE IMAGEN ====== */
if ($tipo === "image") {

    $media_id = $msg["image"]["id"];
    $media_url = "https://graph.facebook.com/v20.0/$media_id";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $media_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response["url"])) {
        $imagenURL = $response["url"];
    }
}

/* ====== CONSULTAR SI EL CLIENTE YA TIENE PEDIDO TEMPORAL ====== */
$sql = "SELECT * FROM pedidos_temp WHERE telefono = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $telefono);
$stmt->execute();
$temp = $stmt->get_result()->fetch_assoc();

/* ====== SI ES NUEVO ====== */
if (!$temp) {

    $sql = "INSERT INTO pedidos_temp (telefono, paso) VALUES (?, 1)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $telefono);
    $stmt->execute();

    enviarMensaje($telefono,
        "¡Hola! Bienvenido a *Piñatería FiestaClick* 🎉.\n\n" .
        "¿Cuál es tu *nombre*?"
    );

    echo json_encode(["status" => "ok"]);
    exit;
}

$paso = $temp["paso"];

/* ====== FLUJO DEL BOT ====== */
switch ($paso) {

    case 1:
        $nombre = $mensaje;

        $sql = "UPDATE pedidos_temp SET nombre=?, paso=2 WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $nombre, $telefono);
        $stmt->execute();

        enviarMensaje($telefono, 
            "Excelente, *$nombre*.\n" .
            "Dime, ¿qué tipo de *piñata* deseas?"
        );
        break;

    case 2:
        $tipo_p = $mensaje;

        $sql = "UPDATE pedidos_temp SET tipo=?, paso=3 WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $tipo_p, $telefono);
        $stmt->execute();

        enviarMensaje($telefono, 
            "Perfecto.\n" .
            "Si tienes *foto de referencia*, envíala ahora.\n" .
            "Si no tienes, escribe: *sin foto*."
        );
        break;

    case 3:
        $ref = ($tipo === "image" && $imagenURL) ? $imagenURL : "sin_foto";

        $sql = "UPDATE pedidos_temp SET referencia=?, paso=4 WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $ref, $telefono);
        $stmt->execute();

        enviarMensaje($telefono,
            "Muy bien.\nAhora cuéntame los *detalles adicionales*."
        );
        break;

    case 4:
        $detalles = $mensaje;

        $sql = "UPDATE pedidos_temp SET detalles=?, paso=5 WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $detalles, $telefono);
        $stmt->execute();

        enviarMensaje($telefono,
            "Perfecto.\n" .
            "¿Cuál será la *forma de pago*?\n• efectivo\n• tarjeta\n• transferencia"
        );
        break;

    case 5:
        $forma_pago = $mensaje;

        $sql = "UPDATE pedidos_temp SET forma_pago=?, paso=6 WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $forma_pago, $telefono);
        $stmt->execute();

        enviarMensaje($telefono,
            "Última pregunta.\n¿Quién *recogerá la piñata*?"
        );
        break;

    case 6:
        $recoge = $mensaje;

        $sql = "UPDATE pedidos_temp SET recoge=?, paso=7 WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $recoge, $telefono);
        $stmt->execute();

        $sql = "SELECT * FROM pedidos_temp WHERE telefono=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $telefono);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();

        $texto =
            "*Resumen de tu pedido:* \n\n" .
            "Nombre: " . $p["nombre"] . "\n" .
            "Tipo: " . $p["tipo"] . "\n" .
            "Referencia: " . ($p["referencia"] === "sin_foto" ? "Sin foto" : "Incluida") . "\n" .
            "Detalles: " . $p["detalles"] . "\n" .
            "Forma de pago: " . $p["forma_pago"] . "\n" .
            "Lo recogerá: " . $p["recoge"] . "\n\n" .
            "¿Deseas *confirmar* este pedido? (si/no)";

        enviarMensaje($telefono, $texto);
        break;

    case 7:

        $respuesta = strtolower($mensaje);

        if ($respuesta === "si" || $respuesta === "sí") {

            $sql = "
                INSERT INTO pedidos_whatsapp
                (telefono, nombre, tipo, referencia, detalles, forma_pago, recoge, fecha_entrega, estado)
                SELECT telefono, nombre, tipo, referencia, detalles, forma_pago, recoge, CURDATE(), 'nuevo'
                FROM pedidos_temp
                WHERE telefono = ?
            ";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $telefono);
            $stmt->execute();

            $sql = "DELETE FROM pedidos_temp WHERE telefono=?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $telefono);
            $stmt->execute();

            enviarMensaje($telefono,
                "¡*Pedido confirmado!* 🎉\nGracias por confiar en FiestaClick."
            );

        } else {

            enviarMensaje($telefono,
                "Pedido cancelado.\nSi deseas iniciar otro pedido, escribe *Hola*."
            );

            $sql = "DELETE FROM pedidos_temp WHERE telefono=?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $telefono);
            $stmt->execute();
        }

        break;
}

echo json_encode(["status" => "ok"]);

/* ====== FUNCIÓN PARA ENVIAR MENSAJES ====== */
function enviarMensaje($telefono, $texto) {
    global $token, $phone_number_id;

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
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
