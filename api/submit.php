<?php
use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\TagModel;
use AmoCRM\Filters\TagsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\TagsCollection;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Dotenv\Dotenv;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\UserTable;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule('crm');

include_once '../vendor/autoload.php';



if (!isset($_POST['name']) || !isset($_POST['phone']) || !isset($_POST['comment'])) {
    exit('INVALID REQUEST');
}

$name = $_POST['name'];
$phone = $_POST['phone'];
$comment = $_POST['comment'];

if (gettype($phone) == gettype('ttt')){
    $phone = preg_replace('/[^0-9]/', '', $phone);
}

if (!file_exists('../.env')) {
    exit('Файл .env не найден!');
}

$dotenv = new Dotenv;
$result = $dotenv->load('../.env');

if ($result === false) {
    var_dump($result);
    exit('Не удалось загрузить .env файл.');
}

$apiClient = new AmoCRMApiClient(
    $_ENV['CLIENT_ID'],
    $_ENV['CLIENT_SECRET'],
    $_ENV['CLIENT_REDIRECT_URI']
);

$apiClient->setAccountBaseDomain($_ENV['ACCOUNT_DOMAIN']);

$rawToken = json_decode(file_get_contents('../token.json'), 1);
$token = new AccessToken($rawToken);

if ($token->hasExpired()) {
    try {
        $newToken = $apiClient->getOAuthClient()->getAccessTokenByRefreshToken($token);
        file_put_contents('../token.json', json_encode($newToken->jsonSerialize(), JSON_PRETTY_PRINT));
        $token = $newToken;
        echo "token обновлен";
    } catch (\Exception $e) {
        exit('Ошибка при обновлении токена: ' . $e->getMessage());
    }
}

$apiClient->setAccessToken($token);

$lead = new LeadModel();
$lead->setName("Заявка с сайта" . date('d.m.Y H:i'));

$tagsCollection = new TagsCollection();
$tag = new TagModel();
$tag->setName('сайт');
$tagsCollection->add($tag);
$tagsService = $apiClient->tags(EntityTypesInterface::LEADS);

$tagsService->add($tagsCollection);

$lead = (new LeadModel)
    ->setName("Заявка с сайта " . date('d.m.Y H:i'))
    ->setTags($tagsCollection)
    ->setCustomFieldsValues(
        (new CustomFieldsValuesCollection)->add(
            (new TextCustomFieldValuesModel)->setFieldId(
                $_ENV['NAME_ID']
            )->setValues(
                (new TextCustomFieldValueCollection)->add(
                    (new TextCustomFieldValueModel)->setValue(
                        $name
                    )
                )
            )
        )->add(
            (new TextCustomFieldValuesModel)->setFieldId(
                $_ENV['COMMENT_ID']
            )->setValues(
                (new TextCustomFieldValueCollection)->add(
                    (new TextCustomFieldValueModel)->setValue(
                        $comment
                    )
                )
            )
        )->add(
            (new NumericCustomFieldValuesModel)->setFieldId(
                $_ENV['PHONE_ID']
            )->setValues(
                (new NumericCustomFieldValueCollection)->add(
                    (new NumericCustomFieldValueModel)->setValue(
                        $phone
                    )
                )
            )
        )->add( 
            (new TextCustomFieldValuesModel)->setFieldId(
                $_ENV['SOURCE_ID']
            )->setValues(
                (new TextCustomFieldValueCollection)->add(
                    (new TextCustomFieldValueModel)->setValue(
                        'Сайт'
                    )
                )
            )
        )
    );
    
try {
    $lead = $apiClient->leads()->addOne($lead);
    echo "Заявка отправлена в AmoCRM";
} catch (AmoCRMApiErrorResponseException $e) {
    echo "Ошибка: " . $e->getMessage();
    var_dump($e->getErrors());
    echo "Ответ API: ";
    var_dump($e->getResponse()->getBody()->getContents());
    die;
}
//Битрикс

$contactQueryURL = "https://b24-dux4ut.bitrix24.ru/rest/1/rygja2shw6bt914d/crm.contact.add.json";
$dealQueryURL = "https://b24-dux4ut.bitrix24.ru/rest/1/rygja2shw6bt914d/crm.deal.add.json";	

$arPhone = (!empty($phone)) ? array(array('VALUE' => $phone, 'VALUE_TYPE' => 'MOBILE')) : array();

$dealName = "Заявка с сайта " . date('d.m.Y H:i');

$contactData = http_build_query(array(
    "fields" => array(
        "NAME" => $name,
        "PHONE" => $phone,
    ),
    'params' => array("REGISTER_SONET_EVENT" => "Y") 
));

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_POST => 1,
    CURLOPT_HEADER => 0,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => $contactQueryURL,
    CURLOPT_POSTFIELDS => $contactData,
));

$contactResult = curl_exec($curl);
curl_close($curl);
$contactResult = json_decode($contactResult, true);

if (array_key_exists('error', $contactResult)) {
    die("Ошибка при создании контакта: " . $contactResult['error_description']);
}

$contactId = $contactResult['result'];

$queryData = http_build_query(array(
    "fields" => array(
        "TITLE" => $dealName,
        "COMMENTS" => $comment,
        "SOURCE_ID" => "Сайт",
        "CONTACT_ID" => $contactId, 
        "TAGS" => "сайт",   
    ),
    'params' => array("REGISTER_SONET_EVENT" => "Y") 
));

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_POST => 1,
    CURLOPT_HEADER => 0,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => $dealQueryURL,
    CURLOPT_POSTFIELDS => $queryData,
));

$result = curl_exec($curl);
curl_close($curl);
$result = json_decode($result, true);

if (array_key_exists('error', $result)) {
    die("Ошибка при сохранении сделки: " . $result['error_description']);
} else {
    echo "Заявка отправлена в Bitrix24";
}



