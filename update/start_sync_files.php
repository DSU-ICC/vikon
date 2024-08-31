<?php

$selfDir = dirname(__FILE__);
require_once $selfDir . '/config.php';
require_once $selfDir . '/helper.php';

$token = filterAccessToken(isset($_POST['access_token']) ? (string) $_POST['access_token'] : '');
$dlHandler = null;

try {
    $headers = array('Accept: application/json', 'Authorization: Bearer ' . $token);
    $response = remoteRequest($filemanagerApiDomen . 'sync/getUsedDirNames', true, false, $headers);

    if ($response->code != 200) {
        $resultBody = array(
            'success' => false,
            'forward_code' => $response->code,
            'message' => 'Не удалось инициировать процедуру синхронизации файлов' . tryExtractFmErrorMessage($response, '. '),
        );

        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    if (!property_exists($response->responseBody, 'directories') || !is_array($response->responseBody->directories)) {
        throw new RuntimeException('Невалидный формат ответа при запросе используемых директорий');
    }

    $directories = $response->responseBody->directories;
    $fsRootPath = $selfDir . '/../files';

    $dh = opendir($fsRootPath);
    if (!$dh) {
        $resultBody = array(
            'success' => false,
            'forward_code' => 500,
            'message' => 'Не удалось просканировать папку files',
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    $dirObjects = array();
    while (($fName = readdir($dh)) !== false) {
        $dirObjects[] = $fName;
    }
    closedir($dh);

    $flippedKnownDirectories = array_flip($directories);
    foreach ($dirObjects as $objectName) {
        $objectPath = $fsRootPath . '/' . $objectName;
        if (
            $objectName != '.'
            && $objectName != '..'
            && !array_key_exists($objectName, $flippedKnownDirectories)
            && is_dir($objectPath)
        ) {
            if (is_link($fsRootPath)) {
                throw new RuntimeException(
                    'В синхронизируемый директории находится ссылка'
                    . $objectPath
                    . ' , которая не может быть безопасно удалена'
                );
            }
            removeDirectory($objectPath);
        }
    }

    $resultBody = array(
        'success' => true,
        'forward_code' => 200,
        'directories' => $directories,
    );
} catch (Exception $ex) {
    $resultBody = array('success' => false, 'forward_code' => 500, 'message' => $ex->getMessage());
}

loadHeaders();
setResponseCode(200);
echo json_encode($resultBody);
