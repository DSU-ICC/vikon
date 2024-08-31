<?php

$selfDir = dirname(__FILE__);
require_once $selfDir . '/config.php';
require_once $selfDir . '/helper.php';

$token = filterAccessToken(isset($_POST['access_token']) ? (string) $_POST['access_token'] : '');
$dlHandler = null;

try {
    $headers = array('Accept: application/json', 'Authorization: Bearer ' . $token);
    $response = remoteRequest($filemanagerApiDomen . 'sync/getNewFileInfo', true, false, $headers);
    if ($response->curlHasError) {
        throw new RuntimeException('CURL ' . $response->curlErrorTxt);
    }

    if (
        $response->code != 200
        || !property_exists($response->responseBody, 'file_name')
        || !property_exists($response->responseBody, 'identity')
        || !property_exists($response->responseBody, 'dir_name')
    ) {
        $resultBody = array(
            'success' => false,
            'forward_code' => $response->code == 200 ? 500 : $response->code,
            'message' => 'Не удалось получить информацию о файле, котрый требуется загрузить '
                . tryExtractFmErrorMessage($response, '. '),
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    if ($response->responseBody->file_name === null && $response->responseBody->identity === null) {
        $resultBody = array(
            'success' => true,
            'forward_code' => 200,
            'done' => true,
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    $fileIdentity = $response->responseBody->identity;
    $fileName = $response->responseBody->file_name;
    $directory = $response->responseBody->dir_name;

    if ($directory !== null && (!is_string($directory) || !preg_match("/^[a-z]{3,4}$/", $directory))) {
        throw new RuntimeException('Невалидное название целевой директории для сохранения файла (' . $directory . ')');
    }

    if (!is_string($fileName) || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
        throw new RuntimeException('Файл с таким названием не может быть сохранен (' . $fileName . ')');
    }

    $headers = array('Accept-Encoding: zip, gzip', 'Authorization: Bearer ' . $token);
    $bin = remoteRequest(
        $filemanagerApiDomen . 'sync/downloadFileBinary?identity=' . $fileIdentity,
        false,
        false,
        $headers
    );
    if ($bin->curlHasError) {
        throw new RuntimeException('CURL ' . $bin->curlErrorTxt);
    }

    if ($bin->code != 200) {
        $bin->responseBody = json_decode($bin->responseBody);
        $resultBody = array(
            'success' => false,
            'forward_code' => $bin->code,
            'message' => 'Не удается скачать файл ' . $fileName . tryExtractFmErrorMessage($bin, '. '),
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    $fsPathDir = $selfDir . '/../' . 'files';
    if ($directory !== null) {
        $fsPathDir = $selfDir . '/../files/' . $directory;
    }

    if (!file_exists($fsPathDir)) {
        if (!mkdir($fsPathDir, 0775)) {
            throw new RuntimeException('Не удалось создать папку "' . $fsPathDir . '" на вашем сервере');
        }
    }

    $fsFilePath = $fsPathDir . '/' . $fileName;
    $dlHandler = fopen($fsFilePath, 'w');
    if ($dlHandler == false || !fwrite($dlHandler, $bin->responseBody)) {
        throw new RuntimeException('Не удается записать файл ' . $fsFilePath . ' на диск', 500);
    }

    $headers = array('Authorization: Bearer ' . $token);
    $response = remoteRequest(
        $filemanagerApiDomen . 'sync/markNewFileAsLoaded?identity=' . $fileIdentity,
        true,
        false,
        $headers
    );
    if ($response->code != 200) {
        $resultBody = array(
            'success' => false,
            'forward_code' => $response->code,
            'message' => 'Не удалось пометить файл' . $fileName . ' как обновленный'
                . tryExtractFmErrorMessage($response, '. '),
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    $resultBody = array(
        'success' => true,
        'forward_code' => 200,
        'message' => 'Файл ' . $fileName . ' загружен',
        'done' => false,
    );
} catch (Exception $ex) {
    $resultBody = array('success' => false, 'forward_code' => 500, 'message' => $ex->getMessage());
}

if ($dlHandler) {
    fclose($dlHandler);
}

loadHeaders();
setResponseCode(200);
echo json_encode($resultBody);
