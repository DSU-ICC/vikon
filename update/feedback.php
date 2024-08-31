<?php
$selfDir = dirname(__FILE__);
require_once $selfDir . '/config.php';

$res['success'] = true;
$res['errorID'] = array();
$res['messages'] = array();

$name = $_POST['name'];
$email = $_POST['email'];
$subject = $_POST['subject'];
$message = $_POST['message'];
$consent = filter_var($_POST['consent'], FILTER_SANITIZE_NUMBER_INT);

if($consent != true){
    $res['success'] = false;
    $res['messages'][] = 'Не получено согласие на обработку персональных данных.';
    $res['errorID'][] = 'consent';
}

$code = filter_var($_POST['captcha'], FILTER_SANITIZE_STRING);
session_start();
if (!isset($_SESSION['captcha']) || strtoupper(trim($_SESSION['captcha'])) != strtoupper(trim($code))) {
    $res['success'] = false;
    $res['errorID'][] = 'captcha';
    $res['messages'][] = 'Неверный код с картинки.';
}
unset($_SESSION['captcha']);

if ($res['success']) {
    $res['success'] = false;

    $headers = array('Accept: application/json');
    $data = getRemoteData($apiDomen . 'oauth2/ClientCredentials', null, true,
        array(
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            ), $headers
    );

    if ($data->success) {
        if (isset($data->info->access_token)) {
            $accessToken = $data->info->access_token;

            try {
                $data = getRemoteData($apiDomen . 'oauth_via_app/feedbackSendMail?access_token='
                    . $accessToken .
                    '&name=' . urlencode($name) .
                    '&email=' . urlencode($email) .
                    '&subject=' . urlencode($subject) .
                    '&message=' . urlencode($message)
                );

                if ($data->info->success) {
                    $res['success'] = true;
                } else {
                    $res['message'] = $data->info->message;
                }

            } catch (Exception $e) {
                $res['message'] = 'Ошибка. '.$e->getMessage();
            }

        } else {
            $res['message'] = 'Ошибка ' . $data->info->message;
        }
    } else {
        $res['message'] = $data->message;
    }
}

loadHeaders();

echo json_encode($res);
