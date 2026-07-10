<?php
/**
 * Pro.Curem — envío de correo por SMTP autenticado (sin librerías externas).
 * Se usa porque mail() de PHP fue rechazada por el servidor de Namecheap.
 *
 * CONFIGURA ESTOS 4 VALORES ANTES DE USAR:
 * Edítalos directamente en el servidor (cPanel → File Manager → Edit),
 * no los compartas con nadie ni los subas a repositorios públicos.
 */

define('SMTP_HOST', 'pro-curem.com'); // servidor de correo nativo de cPanel (no Private Email)
define('SMTP_PORT', 465);                     // 465 = SSL directo
define('SMTP_USER', 'contacto@pro-curem.com'); // la casilla que autentica el envío
define('SMTP_PASS', '6reatPassw0rd$'); // <-- reemplaza esto en el servidor

/**
 * Envía un correo por SMTP. Devuelve true/false; si falla, $error tendrá el detalle.
 */
function smtp_send($to, $subject, $body, $replyTo = null, &$error = null) {
    $timeout = 15;
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
    ]]);

    $socket = @stream_socket_client(
        'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if (!$socket) {
        $error = "No se pudo conectar a " . SMTP_HOST . ":" . SMTP_PORT . " — $errstr ($errno)";
        return false;
    }
    stream_set_timeout($socket, $timeout);

    $read = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $write = function ($cmd) use ($socket) { fwrite($socket, $cmd . "\r\n"); };

    $resp = $read();
    if (substr($resp, 0, 3) !== '220') { $error = "Saludo inválido del servidor: $resp"; fclose($socket); return false; }

    $write('EHLO pro-curem.com');
    $read();

    $write('AUTH LOGIN');
    $read();
    $write(base64_encode(SMTP_USER));
    $read();
    $write(base64_encode(SMTP_PASS));
    $resp = $read();
    if (substr($resp, 0, 3) !== '235') {
        $error = "Autenticación fallida — revisa usuario/contraseña en smtp-mailer.php: $resp";
        $write('QUIT'); fclose($socket); return false;
    }

    $write('MAIL FROM:<' . SMTP_USER . '>');
    $resp = $read();
    if (substr($resp, 0, 3) !== '250') { $error = "MAIL FROM rechazado: $resp"; fclose($socket); return false; }

    $write('RCPT TO:<' . $to . '>');
    $resp = $read();
    if (!in_array(substr($resp, 0, 3), ['250', '251'])) { $error = "RCPT TO rechazado: $resp"; fclose($socket); return false; }

    $write('DATA');
    $resp = $read();
    if (substr($resp, 0, 3) !== '354') { $error = "DATA rechazada: $resp"; fclose($socket); return false; }

    $headers  = "From: Pro.Curem <" . SMTP_USER . ">\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: $subject\r\n";
    if ($replyTo) { $headers .= "Reply-To: $replyTo\r\n"; }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    // Byte-stuffing SMTP: las líneas que empiezan con "." deben duplicarse.
    $bodyEscaped = preg_replace('/^\./m', '..', $body);

    $write($headers . "\r\n" . $bodyEscaped . "\r\n.");
    $resp = $read();
    if (substr($resp, 0, 3) !== '250') { $error = "El servidor rechazó el envío: $resp"; fclose($socket); return false; }

    $write('QUIT');
    fclose($socket);
    return true;
}
