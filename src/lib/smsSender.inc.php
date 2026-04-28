<?php
/*
 * Copyright (c) 2006, Dinahosting S.L.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice, this
 *       list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *     * Neither the name of the Dinahosting S.L. nor the names of its contributors
 *       may be used to endorse or promote products derived from this software
 *       without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (defined("_DINAHOSTING_SMSSENDER_INCLUDED"))
{
    return;
}

/**
 * Para evitar inclusión multiple.
 */
define("_DINAHOSTING_SMSSENDER_INCLUDED", true);
/**
 * url a la que se envian las peticiones.
 */
define("_DINAHOSTING_URL_SEND", "https://dinahosting.com/special/api.php");


/**
 * Se encarga del envio de SMS usando la API de dinahosting.com
 * @version 1.0
 */
class smsSender
{

    /**
     * @var $username
     */
    private $username;

    /**
     * @var $password
     */
    private $password;

    /**
     * @var $account
     */
    private $account;

    /**
     * smsSender constructor.
     * @param $username
     * @param $password
     * @param $account
     */
    public function __construct($username, $password, $account)
    {
        $this->username = $username;
        $this->password = $password;
        $this->account = $account;
    }

    /**
     * Devuelve el credito disponible.
     *
     * @return int : Número de créditos (mensajes) disponibles
     * @throws Exception
     */
    public function getCredit()
    {
        $params = ['account' => $this->account, 'command' => 'Sms_GetCredit'];
        $response = $this->send($params);
        return intval($response->data);
    }

    /**
     * Envia un mensaje a un numero
     *
     * @param array $numbers : Array con los números de teléfono destino
     * @param $message : Texto del mensaje para enviar
     * @param null $when : Fecha programada para el envio
     * @throws Exception
     */
    function sendMessage(array $numbers, $message, $when = null)
    {
        $params = [
            'responseType' => 'Json',
            'account' => $this->account,
            'contents' => $message,
            'to' => $numbers,
            'from' => $this->account,
            'command' => 'Sms_Send_Bulk_Limited_Unicode'
        ];

        if (!empty($when))
        {
            $params['when'] = $when;
        }

        $this->send($params);
    }

    /**
     * Realiza la petición remota.
     *
     * @param $params : Array asociativo con los nombres de los parametros y sus valores.
     * @return bool|mixed|string : El resultado de la petición
     * @throws Exception
     */
    public function send($params)
    {
        $params['responseType'] = 'Json';

        $args = http_build_query($params, '', '&');
        $headers = array();

        $handle = curl_init(_DINAHOSTING_URL_SEND);
        if ($handle === false) // error starting curl
        {
            throw new Exception('0 - Couldn\'t start curl');
        }
        else
        {
            curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_URL, _DINAHOSTING_URL_SEND);

            curl_setopt($handle, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

            curl_setopt($handle, CURLOPT_TIMEOUT, 60);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT,
                        4); // set higher if you get a "28 - SSL connection timeout" error

            curl_setopt($handle, CURLOPT_HEADER, true);
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

            $curlversion = curl_version();
            curl_setopt($handle, CURLOPT_USERAGENT, 'PHP ' . phpversion() . ' + Curl ' . $curlversion['version']);
            curl_setopt($handle, CURLOPT_REFERER, null);

            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER,
                        false); // set false if you get a "60 - SSL certificate problem" error

            curl_setopt($handle, CURLOPT_POSTFIELDS, $args);
            curl_setopt($handle, CURLOPT_POST, true);

            $response = curl_exec($handle);

            if ($response)
            {
                $response = substr($response, strpos($response, "\r\n\r\n") + 4); // remove http headers
            }
            else // http response code != 200
            {
                throw new Exception(curl_errno($handle) . ' - ' . curl_error($handle));
            }

            curl_close($handle);
        }
        $response = json_decode($response);


        if ($response->responseCode != 1000)
        {
            throw new Exception(json_encode($response->errors));
        }

        return $response;
    }
}