<?php


namespace SilverStripe\Gatsby\GridField;


use SilverStripe\Assets\File;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use Exception;

class SyncPreviewFilterButton implements GridField_DataManipulator, GridField_HTMLProvider, GridField_ActionProvider
{
    use Injectable;

    public function getActions($gridField)
    {
        return ['preview'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'preview') {
            if (!isset($data['since'])) {
                throw new Exception('No date provided to "preview" action');
            }
            $gridField->State->PreviewDate = strtotime($data['since']);
        }
    }

    public function getHTMLFragments($gridField)
    {
        $date = $this->getDateFromGridField($gridField);
        $list = $gridField->getManipulatedList()->limit(null);
        $count = $list->count();
        $size = 0;
        foreach ($list->chunkedFetch() as $item) {
            if ($item->ObjectClass === File::class) {
                $size += $item->Object()->getAbsoluteSize();
            }
        }
        return [
            'before' => FieldList::create(
                FieldGroup::create(
                    DatetimeField::create('since', 'Preview sync from a date and time', $date->Rfc2822()),
                    GridField_FormAction::create($gridField, 'preview', 'Show results', 'preview', [])
                ),
                HeaderField::create('previewLabel', 'Showing sync from ' . $date->FormatFromSettings(), 2),
                HeaderField::create('previewStats',
                    sprintf(
                        'Total nodes: [%s] Assets payload: [%s]',
                        $count,
                        File::format_size($size)
                    ),
                    3
                )
            )->forTemplate()
        ];
    }

    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        $date = $this->getDateFromGridField($gridField);
        $dateStr = $date->Rfc2822();

        return $dataList->filter([
            'LastEdited:GreaterThan' => $dateStr,
        ]);
    }

    /**
     * @return int
     */
    private function getDefaultDateTime(): int
    {
        return strtotime('-1 day');
    }

    private function getDateFromGridField(GridField $gridField): DBDatetime
    {
        $dateStr = (string) $gridField->State->PreviewDate;
        if (empty($dateStr)) {
            $dateStr = $this->getDefaultDateTime();
        }

        /* @var DBDatetime $date */
        $date = DBField::create_field('DBDatetime', $dateStr);

        return $date;
    }
}
