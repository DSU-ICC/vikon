<?php
$selfDir = dirname(__FILE__);
require_once  $selfDir . '/config.php';

$res['success'] = false;

if (empty($_POST['access_token']) && !empty($_GET['access_token'])) { //переходный момент - можно удалить после 01.07.2023
    $token = filterAccessToken($_GET['access_token']);
}
if (empty($token)) { //и условие
    $token = filterAccessToken($_POST['access_token']);
}

try {
    $headers = array(
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    );

    $response = remoteRequest($apiDomen . 'pull_updates/assist/updateEndedSuccessJson', true, array(), $headers);
    if (!$response->curlHasError) {
        if ($response->code == 200) {
            $resultBody = array(
                'success' => true,
                'forward_code' => 200,
            );
        } else {
            $resultBody = array(
                'success' => false,
                'forward_code' => $response->code,
                'message' => !empty($response->responseBody->message )
                    ? $response->responseBody->message
                    : 'Неизвестная ошибка'
            );
        }
    } else {
        throw new RuntimeException( 'CURL ' . $response->curlErrorTxt);
    }
} catch (Exception $ex) {
    $resultBody = array('success' => false, 'forward_code' => 500, 'message' => $ex->getMessage());
}

loadHeaders();
setResponseCode(200);
echo json_encode($resultBody);
