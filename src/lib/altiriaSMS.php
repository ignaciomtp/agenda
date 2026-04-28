<?php
function enviarSMSAltiria($telefonos, $mensaje, $usuario, $clave, $senderId = '', $debug = false) {
    // URL base de la API REST de Altiria
    $baseUrl = 'https://www.altiria.net:8443/apirest/ws';
    $ch = curl_init($baseUrl . '/sendSms');

    $credentials = [
        'login'  => $usuario,
        'passwd' => $clave
    ];

    // Aceptamos múltiples destinos separados por comas o como array
    if (is_array($telefonos)) {
        $destinations = $telefonos;
    } else {
        $destinations = explode(',', $telefonos);
    }

    $jsonMessage = [
        'msg' => substr($mensaje, 0, 160), // máximo 160 caracteres
        'senderId' => $senderId
    ];

    $jsonData = [
        'credentials' => $credentials,
        'destination' => $destinations,
        'message'     => $jsonMessage
    ];
    //Se construye el mensaje JSON
    $jsonDataEncoded = json_encode($jsonData);

    //Se construye el mensaje JSON
	$jsonDataEncoded = json_encode($jsonData);
	 
	//Indicamos que nuestra petici�n sera Post
	curl_setopt($ch, CURLOPT_POST, 1);

	//Se fija el tiempo m�ximo de espera para conectar con el servidor (5 segundos)
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	 
	//Se fija el tiempo m�ximo de espera de la respuesta del servidor (60 segundos)
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	 
	//Para que la peticion no imprima el resultado como un 'echo' comun
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	 
	//Se a�ade el JSON al cuerpo de la petici�n codificado en UTF-8
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
	 
	//Se fija el tipo de contenido de la peticion POST
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=UTF-8']);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($debug) {
        echo 'HTTP status: ' . $statusCode . '<br>';
        echo 'Respuesta: ' . htmlspecialchars($response) . '<br>';
    }

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    $json_parsed = json_decode($response);
    if (!$json_parsed || $json_parsed->status != '000') {
        throw new Exception("Error enviando SMS: " . $response);
    }

    return $json_parsed;
}

/**
 * Consulta el saldo restante de sms del usuario en Altiria
 *
 * @param [type] $usuario   Usuario
 * @param [type] $clave     Clave del usuario
 * @param boolean $debug
 * @return void
 */
function consultarSaldoAltiria($usuario, $clave, $debug = false) {
    $url = 'https://www.altiria.net:8443/apirest/ws/getCredit';

    $ch = curl_init($url);

    $credentials = [
        'login'  => $usuario,
        'passwd' => $clave
    ];

    $jsonData = json_encode(['credentials' => $credentials]);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=UTF-8']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // solo en pruebas
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Error consultando saldo: ' . curl_error($ch));
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($statusCode != 200) {
        throw new Exception('Error HTTP al consultar saldo: ' . $statusCode);
    }

    $json = json_decode($response, true);

    if ($debug) {
        echo '<pre>';
        print_r($json);
        echo '</pre>';
    }

    return $json['credit'] ?? null; // Devuelve el saldo disponible
}

