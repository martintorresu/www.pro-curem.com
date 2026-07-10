<?php
/**
 * Pro.Curem — manejador del formulario de demo/cotización.
 * Envía el lead a contacto@pro-curem.cl vía SMTP autenticado (smtp-mailer.php),
 * porque mail() de PHP fue rechazada por el servidor de Namecheap.
 * Al finalizar, redirige a gracias.html, que dispara la conversión "Contacto" de Google Ads.
 */

require __DIR__ . '/smtp-mailer.php';

$destinatario = "contacto@pro-curem.com";

function limpiar($valor) {
    $valor = trim($valor ?? "");
    $valor = str_replace(["\r", "\n"], " ", $valor); // evita inyección de cabeceras
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

// Honeypot anti-spam: si este campo oculto viene lleno, es un bot.
if (!empty($_POST['empresa_web'])) {
    header('Location: gracias.html');
    exit;
}

$nombre   = limpiar($_POST['nombre'] ?? '');
$empresa  = limpiar($_POST['empresa'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$telefono = limpiar($_POST['telefono'] ?? '');
$plan     = limpiar($_POST['plan'] ?? 'No especificado');

if (!$nombre || !$empresa || !$email) {
    header('Location: index.html?form_error=1#contacto');
    exit;
}

$asunto = "Nuevo lead Pro.Curem - $empresa ($plan)";
$cuerpo = "Nombre: $nombre\n"
        . "Empresa: $empresa\n"
        . "Email: $email\n"
        . "Teléfono: " . ($telefono ?: "—") . "\n"
        . "Plan de interés: $plan\n";

$error = null;
$enviado = smtp_send($destinatario, $asunto, $cuerpo, $email, $error);

if ($enviado) {
    header('Location: gracias.html');
    exit;
} else {
    // Deja un registro del error en el log de PHP para que puedas revisarlo en cPanel.
    error_log("Pro.Curem contact-form.php SMTP error: $error");
    header('Location: index.html?form_error=server#contacto');
    exit;
}
