<?php


namespace SilverStripe\Gatsby\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use SilverStripe\Core\Path;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Gatsby\Admins\WebhooksAdmin;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;

class Webhook extends DataObject
{
    const EVENT_PREVIEW = 'PREVIEW';
    const EVENT_PUBLISH = 'PUBLISH';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
        'URL' => 'Varchar(255)',
        'Method' => "Enum('GET, POST', 'POST')",
        'Event' => "Enum('" . self::EVENT_PREVIEW . ", " . self::EVENT_PUBLISH . "', '" . self::EVENT_PREVIEW . "')",
        'UseJSON' => 'Boolean',
        'JSONPayload' => 'Text',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Title' => 'Label',
        'URL' => 'URL',
        'Event' => 'Called on',
    ];

    /**
     * @var string
     */
    private static $table_name = 'Webhook';

    /**
     * @var string
     */
    private static $singular_name = 'Webhook';

    /**
     * @var string
     */
    private static $plural_name = 'Webhooks';

    /**
     * @var string
     */
    private static $default_sort = 'ID ASC';

    /**
     * @var bool[]
     */
    private static $indexes = [
        'Event' => true
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $testLink = WebhooksAdmin::singleton()->Link(Path::join(
            str_replace('\\', '-', static::class),
            'invoke?id=' . $this->ID
        ));
        $fields = FieldList::create(TabSet::create('Root'));
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Webhook label (for reference only)'),
            TextField::create('URL', 'Webhook URL (beginning with https://)'),
            DropdownField::create('Method', 'Request method', ArrayLib::valuekey(['GET', 'POST'])),
            CheckboxField::create('UseJSON', 'Include a JSON payload'),
            TextField::create('JSONPayload', 'JSON Payload')
                ->displayIf('UseJSON')
                    ->isChecked()
                    ->andIf('Method')
                        ->isEqualTo('POST')
                ->end(),
            DropdownField::create('Event', 'Webhook gets called on', ArrayLib::valuekey([
                self::EVENT_PREVIEW,
                self::EVENT_PUBLISH,
            ])),
            LiteralField::create(
                'invoke',
                '<a class="btn font-icon-rocket action btn-outline-primary" href="' . $testLink . '">Invoke webhook</a>'
            )
        ]);
        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * @return ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();
        $url = $this->URL;
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result->addFieldError('URL', 'Please enter a valid URL');
        }

        if ($this->UseJSON && $this->JSONPayload && $this->Method !== 'POST') {
            $result->addFieldError('UseJSON', 'JSON payloads are only allowed on POST requests');
        }

        return $result;
    }

    /**
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function invoke(): ResponseInterface
    {
        $client = new Client();
        $options = [];
        if ($this->UseJSON && $this->JSONPayload) {
            $options[RequestOptions::JSON] = json_decode($this->JSONPayload);
        }
        $res = $client->request($this->Method, $this->URL, $options);

        return $res;
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null
     * @param array
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

}
