<?php

/**
 * Table Maker plugin for Craft CMS 3.x
 *
 * A user-definable table field type for Craft CMS
 *
 * @link      http://www.supercooldesign.co.uk/
 * @copyright Copyright (c) 2018 Supercool Ltd
 */

namespace supercool\tablemaker\fields;

use craft\fields\data\ColorData;
use craft\helpers\DateTimeHelper;
use craft\web\assets\tablesettings\TableSettingsAsset;
use supercool\tablemaker\TableMaker;
use supercool\tablemaker\assetbundles\field\FieldAsset;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;
use craft\helpers\Template;

/**
 * @author    Supercool Ltd
 * @package   TableMaker
 * @since     1.0.0
 */

class TableMakerField extends Field
{
    // Public Properties
    // =========================================================================

    public $columnsLabel;
    public $columnsInstructions;
    public $columnsAddRowLabel;
    public $rowsLabel;
    public $rowsInstructions;
    public $rowsAddRowLabel;


    // Static Methods
    // =========================================================================

    /**
     * Returns the display name of this field type.
     *
     * @return string The display name of this field type.
     */
    public static function displayName(): string
    {
        return Craft::t('tablemaker', 'Table Maker');
    }


    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        return $rules;
    }


    /**
     * Returns the column type that this field should get within the content table.
     *
     * This method will only be called if [[hasContentColumn()]] returns true.
     *
     * @return string The column type. [[\yii\db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)` and `string(100)` becomes `varchar(100)`. `not null` will automatically be
     * appended as well.
     * @see \yii\db\QueryBuilder::getColumnType()
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * Normalizes a cell’s value.
     * Taken from craft\fields\Table::_normalizeCellValue()
     *
     * @param string $type The cell type
     * @param mixed $value The cell value
     * @return mixed
     * @see normalizeValue()
     */
    public function normalizeCellValue(string $type, $value)
    {
        switch ($type) {
            case 'color':
                if ($value instanceof ColorData) {
                    return $value;
                }

                if (!$value || $value === '#') {
                    return null;
                }

                $value = strtolower($value);

                if ($value[0] !== '#') {
                    $value = '#' . $value;
                }

                if (strlen($value) === 4) {
                    $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
                }

                $color = new ColorData($value);
                return $color->__toString();

            case 'date':
            case 'time':
                return json_encode(DateTimeHelper::toIso8601($value)) ?: null;
        }

        return $value;
    }


    /**
     * Normalizes the field’s value for use.
     *
     * This method is called when the field’s value is first accessed from the element. For example, the first time
     * `entry.myFieldHandle` is called from a template, or right before [[getInputHtml()]] is called. Whatever
     * this method returns is what `entry.myFieldHandle` will likewise return, and what [[getInputHtml()]]’s and
     * [[serializeValue()]]’s $value arguments will be set to.
     *
     * @param mixed                 $value   The raw field value
     * @param ElementInterface|null $element The element the field is associated with, if there is one
     *
     * @return mixed The prepared field value
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {

        if ( !is_array($value) )
        {
            $value = Json::decode($value);
        }

        if ( !isset($value['rows']) )
        {
            $value['rows'] = [];
        }

        // make an html table
        $html = '
            <table>
                <thead>
                    <tr>
        ';

        if ( !empty($value['columns']) )
        {
            foreach ($value['columns'] as &$col)
            {
                $html .= '<th align="' . (isset($col['align']) ? $col['align'] : "left") . '" width="' . (isset($col['width']) ? $col['width'] : "") . '">' . (isset($col['heading']) ? $col['heading'] : "") . '</th>';
                //json decode options array
                if(isset($col['options']) && !is_array($col['options'])) $col['options'] = Json::decode($col['options']);
                unset($col);
            }
        }

        $html .= '
                    </tr>
                </thead>

                <tbody>

        ';

        if ( !empty($value['rows']) )
        {

            foreach ($value['rows'] as $row)
            {

                $html .= '<tr>';

                $i = 0;
                foreach ($row as $key => $cell) {
                    $type = isset($value['columns'][$key]['type']) ? $value['columns'][$key]['type'] : "singleline";
                    $cell = $this->normalizeCellValue($type, $cell);

                    $align = $value['columns'][$key]['align'] ?? $value['columns'][$i]['align'];
                    $html .= '<td align="' . $align . '">' . $cell . '</td>';
                    $i++;
                }

                $html .= '</tr>';

            }

        }

        $html .= '

                </tbody>

            </table>
        ';

        $value['table'] = Template::raw($html);

        return $value;
    }
    

    /**
     * Modifies an element query.
     *
     * This method will be called whenever elements are being searched for that may have this field assigned to them.
     *
     * If the method returns `false`, the query will be stopped before it ever gets a chance to execute.
     *
     * @param ElementQueryInterface $query The element query
     * @param mixed                 $value The value that was set on this field’s corresponding [[ElementCriteriaModel]] param,
     *                                     if any.
     *
     * @return null|false `false` in the event that the method is sure that no elements are going to be found.
     */
    public function serializeValue($value, ElementInterface $element = null)
    {

        if ( !empty($value['rows']) && is_array($value['rows']) )
        {
            // drop keys from the rows array
            $value['rows'] = array_values($value['rows']);

            // loop each row
            foreach ($value['rows'] as &$row)
            {
                if ( is_array($row) )
                {
                    // drop those array keys
                    $row = array_values($row);
                }
            }
        }

        // // drop keys from the columns array
        if ( !empty($value['columns']) && is_array($value['columns']) )
        {
            $value['columns'] = array_values($value['columns']);
        }

        return parent::serializeValue($value, $element);
    }


    /**
     * Returns the component’s settings HTML.
     *
     * @return string|null
     */
    public function getSettingsHtml()
    {
        // Render the settings template
        return Craft::$app->getView()->renderTemplate(
            'tablemaker/_components/fields/_settings',
            [
                'field' => $this,
            ]
        );
    }


    /**
     * Returns the field’s input HTML.
     *
     * @param mixed                 $value           The field’s value. This will either be the [[normalizeValue() normalized value]],
     *                                               raw POST data (i.e. if there was a validation error), or null
     * @param ElementInterface|null $element         The element the field is associated with, if there is one
     *
     * @return string The input HTML.
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $view = Craft::$app->getView();

        // Register our asset bundle
        $view->registerAssetBundle(FieldAsset::class);

        $name = $this->handle;

        $columnsInput = $name.'[columns]';
        $rowsInput    = $name.'[rows]';

        $columnsInputId = $name.'-columns';
        $rowsInputId = $name.'-rows';

        // make input
        $input = '<input class="table-maker-field" type="hidden" name="'.$name.'" value="">';
        
        // get columns from db or fall back to default
        if ( !empty($value['columns']) )
        {
            foreach ($value['columns'] as $key => $val) {
                $val['type'] = isset($val['type']) ? $val['type'] : "singleline";
                $columns['col'.$key] = array(
                    'heading' => $val['heading'],
                    'align' => $val['align'],
                    'width' => $val['width'],
                    'type' => $val['type']
                );

                if ($val['type'] === 'select') {
                    if (!isset($val['options'])) {
                        $columns['col'.$key]['options'] = [];
                    } else if (is_string($val['options'])) {
                        $columns['col'.$key]['options'] = Json::decode($val['options']);
                    }
                    else {
                        $columns['col'.$key]['options'] = $val['options'];
                    }
                } else {
                    unset($columns['col'.$key]['options']);
                }
            }
        }
        else
        {
            $columns = array(
                'col0' => array(
                    'heading' => '',
                    'align' => '',
                    'width' => '',
                    'type' => 'singleline'
                )
            );
        }


        // get rows from db or fall back to default
        if ( !empty($value['rows']) )
        {
            // walk down the rows and cells appending 'row' to the rows' keys
            // and 'col' to the cells' keys
            foreach ($value['rows'] as $rowKey => $rowVal) {
                foreach ($rowVal as $colKey => $colVal) {
                    $type = $value['columns'][$colKey]['type'];
                    $rows['row'.$rowKey]['col'.$colKey] = in_array($type, ['date', 'time'], true) ? DateTimeHelper::toIso8601($colVal) : $colVal;
                }
            }
        }
        else
        {
            $rows = array('row0' => array());
        }

        $typeOptions = [
            'checkbox' => Craft::t('app', 'Checkbox'),
            'color' => Craft::t('app', 'Color'),
            'date' => Craft::t('app', 'Date'),
            'select' => Craft::t('app', 'Dropdown'),
            'email' => Craft::t('app', 'Email'),
            'lightswitch' => Craft::t('app', 'Lightswitch'),
            'multiline' => Craft::t('app', 'Multi-line text'),
            'number' => Craft::t('app', 'Number'),
            'singleline' => Craft::t('app', 'Single-line text'),
            'time' => Craft::t('app', 'Time'),
            'url' => Craft::t('app', 'URL'),
        ];

        // Make sure they are sorted alphabetically (post-translation)
        asort($typeOptions);

        // prep col settings
        $columnSettings = array(
            'heading' => array(
                'heading' => Craft::t('tablemaker', 'Heading'),
                'type' => 'singleline'
            ),
            'width' => array(
                'heading' => Craft::t('tablemaker', 'Width'),
                'class' => 'code',
                'type' => 'singleline',
                'width' => 50
            ),
            'align' => array(
                'heading' => Craft::t('tablemaker', 'Alignment'),
                'class' => 'thin',
                'type' => 'select',
                'options' => array(
                    'left'   => Craft::t('tablemaker', 'Left'),
                    'center' => Craft::t('tablemaker', 'Center'),
                    'right'  => Craft::t('tablemaker', 'Right')
                )
            ),
            'type' => array(
                'heading' => Craft::t('tablemaker', 'Type'),
                'class' => 'thin',
                'type' => 'select',
                'options' => $typeOptions
            )
        );

        $dropdownSettingsCols = [
            'label' => [
                'heading' => Craft::t('app', 'Option Label'),
                'type' => 'singleline',
                'autopopulate' => 'value',
                'class' => 'option-label',
            ],
            'value' => [
                'heading' => Craft::t('app', 'Value'),
                'type' => 'singleline',
                'class' => 'option-value code',
            ],
            'default' => [
                'heading' => Craft::t('app', 'Default?'),
                'type' => 'checkbox',
                'radioMode' => true,
                'class' => 'option-default thin',
            ],
        ];

        $dropdownSettingsHtml = Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'editableTableField', [
            [
                'label' => Craft::t('app', 'Dropdown Options'),
                'instructions' => Craft::t('app', 'Define the available options.'),
                'id' => '__ID__',
                'name' => '__NAME__',
                'addRowLabel' => Craft::t('app', 'Add an option'),
                'cols' => $dropdownSettingsCols,
                'initJs' => false,
            ]
        ]);

        // init the js
        $view->registerAssetBundle(TableSettingsAsset::class);
        $view->registerJs('new Craft.TableMaker(' .
            Json::encode($view->namespaceInputId($name), JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($view->namespaceInputId($columnsInputId), JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($view->namespaceInputId($rowsInputId), JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($view->namespaceInputName($columnsInput), JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($view->namespaceInputName($rowsInput), JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($columns, JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($rows, JSON_UNESCAPED_UNICODE).', ' .
            Json::encode($columnSettings, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($dropdownSettingsHtml, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($dropdownSettingsCols, JSON_UNESCAPED_UNICODE) .
            ');');

        // render the two tables
        $fieldSettings = $this->getSettings();
        $columnsField = $view->renderTemplateMacro('_includes/forms', 'editableTableField', array(
            array(
                'label' => $fieldSettings['columnsLabel'] ? Craft::t('tablemaker', $fieldSettings['columnsLabel']) : Craft::t('tablemaker', 'Table Columns'),
                'instructions'  => $fieldSettings['columnsInstructions'] ? Craft::t('tablemaker', $fieldSettings['columnsInstructions']) : Craft::t('tablemaker', 'Define the columns your table should have.'),
                'id'    => $columnsInputId,
                'name'  => $columnsInput,
                'cols'  => $columnSettings,
                'rows'  => $columns,
                'addRowLabel'   => $fieldSettings['columnsAddRowLabel'] ? Craft::t('tablemaker', $fieldSettings['columnsAddRowLabel']) : Craft::t('tablemaker', 'Add a column'),
                'initJs'    => false
            )
        ));

        $rowsField = $view->renderTemplateMacro('_includes/forms', 'editableTableField', array(
            array(
                'label' => $fieldSettings['rowsLabel'] ? Craft::t('tablemaker', $fieldSettings['rowsLabel']) : Craft::t('tablemaker', 'Table Content'),
                'instructions'  => $fieldSettings['rowsInstructions'] ? Craft::t('tablemaker', $fieldSettings['rowsInstructions']) : Craft::t('tablemaker', 'Input the content of your table.'),
                'id'                => $rowsInputId,
                'name'              => $rowsInput,
                'cols'              => $columns,
                'rows'              => $rows,
                'addRowLabel'       => $fieldSettings['rowsAddRowLabel'] ? Craft::t('tablemaker', $fieldSettings['rowsAddRowLabel']) : Craft::t('tablemaker', 'Add a row'),
                'initJs'            => false
            )
        ));

        return $input . $columnsField . $rowsField;
    }


}
