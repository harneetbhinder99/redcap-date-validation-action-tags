<?php
/**
 * REDCap External Module: DateValidationActionTags
 * Action tags to validate date and date time entries as @FUTURE, @NOTPAST, @PAST, @NOTFUTURE.
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\DateValidationActionTags;

use ExternalModules\AbstractExternalModule;

class DateValidationActionTags extends AbstractExternalModule
{
    protected $is_survey = 0;

    protected static $Tags = array(
        '@FUTURE' => array('comparison'=>'gt', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, uses <em>today + 1</em> (or <em>now</em>) as range minimum.<br>Current date (time) is NOT within the allowed range.'),
        '@NOTPAST' => array('comparison'=>'gte', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, equivalent to using <em>today</em> (or <em>now</em>) as range minimum.'),
        '@PAST' => array('comparison'=>'lt', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, uses <em>today - 1</em> (or <em>now</em>) as range maximum.<br>Current date (time) is NOT within the allowed range.'),
        '@NOTFUTURE' => array('comparison'=>'lte', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, equivalent to using <em>today</em> (or <em>now</em>) as range maximum.')
    );

    public function redcap_every_page_before_render($project_id) {
        if (empty($project_id)) return;
        
        if (PAGE=='DataEntry/index.php' || PAGE=='surveys/index') {
            global $Proj;

            // $pattern /(@FUTURE|@NOTPAST|@PAST|@NOTFUTURE)\s/
            $pattern = '/('.implode('|', array_keys(static::$Tags)).')/';
            
            foreach ($Proj->metadata as $field => $attrs) {
                if (strpos($attrs['element_validation_type'], 'date')!==0) continue; // skip if not a date or datetime field
                $min = $attrs['element_validation_min'];
                $max = $attrs['element_validation_max'];
                $isDtTm = (strpos($attrs['element_validation_type'], 'time')) ? true : false;
                
                $matches = array();
                if (preg_match($pattern, $attrs['misc'], $matches)) {

                    switch ($matches[0]) {
                        case '@NOTPAST': // min today/now
                            if (empty($min)) {
                                $Proj->metadata[$field]['element_validation_min'] = ($isDtTm) ? 'now' : 'today';
                            }
                            break;
                        
                        case '@NOTFUTURE': // max is today/now
                            if (empty($max)) {
                                $Proj->metadata[$field]['element_validation_max'] = ($isDtTm) ? 'now' : 'today';
                            }
                            break;

                        case '@FUTURE': // min is tomorrow, use now if datetime
                            if (empty($min)) {
                                if ($isDtTm) {
                                    $Proj->metadata[$field]['element_validation_min'] = 'now';
                                } else {
                                    $tomorrow = new \DateTime();
                                    $tomorrow->add(new \DateInterval('P1D'));
                                    $Proj->metadata[$field]['element_validation_min'] = $tomorrow->format('Y-m-d');
                                }
                            }
                            break;
                        
                        case '@PAST': // max is yesterday, use now if datetime
                            if (empty($max)) {
                                if ($isDtTm) {
                                    $Proj->metadata[$field]['element_validation_max'] = 'now';
                                } else {
                                    $yesterday = new \DateTime();
                                    $yesterday->sub(new \DateInterval('P1D'));
                                    $Proj->metadata[$field]['element_validation_max'] = $yesterday->format('Y-m-d');
                                }
                            }
                        break;
                        
                        default:
                            break;
                    }
                }
            }

        } else if (PAGE==='Design/action_tag_explain.php') {
            global $lang;
            $lastActionTagDesc = end(\Form::getActionTags());

            // which $lang element is this?
            $langElement = array_search($lastActionTagDesc, $lang);
            
            foreach (static::$Tags as $tag => $tagAttr) {
                $lastActionTagDesc .= "</td></tr>";
                $lastActionTagDesc .= $this->makeTagTR($tag, $tagAttr['description']);
            }                        
            $lang[$langElement] = rtrim(rtrim(rtrim(trim($lastActionTagDesc), '</tr>')),'</td>');
        }
    }
    
    /**
     * Make a table row for an action tag copied from 
     * v8.5.0/Design/action_tag_explain.php
     * @global type $isAjax
     * @param type $tag
     * @param type $description
     * @return type
     */
    protected function makeTagTR($tag, $description) {
        global $isAjax, $lang;
        return \RCView::tr(array(),
            \RCView::td(array('class'=>'nowrap', 'data-cell'=>'button', 'style'=>'text-align:center;background-color:#f5f5f5;color:#912B2B;padding:7px 15px 7px 12px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-right:0;'),
                ((!$isAjax || (isset($_POST['hideBtns']) && $_POST['hideBtns'] == '1')) ? '' :
                    \RCView::button(array('class'=>'btn btn-xs btn-rcred', 'style'=>'', 'onclick'=>"$('#field_annotation').val(trim('".js_escape($tag)." '+$('#field_annotation').val())); highlightTableRowOb($(this).parentsUntil('tr').parent(),2500);"), $lang['design_171'])
                )
            ) .
            \RCView::td(array('class'=>'nowrap', 'data-cell'=>'name', 'style'=>'background-color:#f5f5f5;color:#912B2B;padding:7px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-left:0;border-right:0;'),
                $tag
            ) .
            \RCView::td(array('data-cell'=>'desc', 'style'=>'font-size:12px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;'),
                '<i class="fas fa-cube mr-1"></i>'.$description
            )
        );
    }
}
