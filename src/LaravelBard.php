<?php

declare(strict_types=1);

namespace AdityaDees\LaravelBard;

use AdityaDees\LaravelBard\Exceptions\ErrorException;

/**
 * LaravelBard
 *
 * @author Aditya Dharmawan Saputra @adityadees
 *
 */

class LaravelBard
{
    private $proxies;
    private $timeout;
    private $token;
    private $session;
    private $conversation_id;
    private $response_id;
    private $choice_id;
    private $reqid;
    private $SNlM0e;

    public function __construct($timeout = 60, $proxies = null, $session = null)
    {
        $this->proxies = $proxies;
        $this->timeout = $timeout;
        $this->token = config('laravel-bard.bard_token');
        $headers = [
            "Host: bard.google.com",
            "X-Same-Domain: 1",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36",
            "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
            "Origin: https://bard.google.com",
            "Referer: https://bard.google.com/",
        ];
        $this->reqid = (int) rand(pow(10, 3 - 1), pow(10, 3) - 1);
        $this->conversation_id = "";
        $this->response_id = "";
        $this->choice_id = "";

        if ($session === null) {
            $this->session = curl_init();
            curl_setopt_array($this->session, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_COOKIE => "__Secure-1PSID=" . $this->token,
                CURLOPT_RETURNTRANSFER => true,
            ]);
        } else {
            $this->session = $session;
        }

        $this->SNlM0e = $this->_get_snim0e();
    }

    private function _get_snim0e()
    {
        if (!$this->token || substr($this->token, -1) !== ".") {
            throw ErrorException::invalidToken();
        }
        curl_setopt_array($this->session, [
            CURLOPT_URL => "https://bard.google.com/",
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_PROXY => $this->proxies,
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $resp = curl_exec($this->session);
        if (curl_getinfo($this->session, CURLINFO_HTTP_CODE) !== 200) {
            throw ErrorException::errorResponse(curl_getinfo($this->session, CURLINFO_HTTP_CODE));
        }
        preg_match('/"SNlM0e":"(.*?)"/', $resp, $matches);
        if (!$matches) {
            throw ErrorException::snimoeValueNotFound();
        }
        return $matches[1];
    }

    public function set_conversation_id(string $conversation_id)
    {
        $this->conversation_id = $conversation_id;
    }

    public function get_answer($input_text)
    {
        $params = [
            "bl" => "boq_assistant-bard-web-server_20230801.12_p0",
            "_reqid" => (string) $this->reqid,
            "rt" => "c",
        ];
        $input_text_struct = [
            [$input_text],
            null,
            [$this->conversation_id, $this->response_id, $this->choice_id],
        ];
        $data = [
            "f.req" => json_encode([null, json_encode($input_text_struct)]),
            "at" => $this->SNlM0e,
        ];

        curl_setopt_array($this->session, [
            CURLOPT_URL => "https://bard.google.com/_/BardChatUi/data/assistant.lamda.BardFrontendService/StreamGenerate?" . http_build_query($params),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_PROXY => $this->proxies,
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $resp = curl_exec($this->session);

        if (curl_errno($this->session)) {
            throw ErrorException::curlError(curl_error($this->session));
        }

        preg_match('/rc_.*?```json(.*?)```/', $resp, $matches);

        if (!isset($matches[1])) {
            return ["content" => "Response Error: " . $resp . "."];
        }

        $this->reqid += 100000;

        //return response directly allow user to process themselves
        return $matches;
    }
}
