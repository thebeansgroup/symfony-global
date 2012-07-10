<?php
class AdminFormSchemaFormatter extends sfWidgetFormSchemaFormatter 
{
  protected
    $rowFormat = '<div class="sf_admin_form_row sf_admin_text">%error%<div>%label%%field%<br />%help%</div></div>',
    $helpFormat = '<span class="help">%help%</span><br />',
    $errorRowFormat = '<div>%errors%</div>',
    $errorListFormatInARow = '<ul class="error_list">%errors%</ul>',
    $errorRowFormatInARow = '<li>%error%</li>',
    $namedErrorRowFormatInARow = '%name%: %error%<br />',
    $decoratorFormat = '<div id="formContainer">%content%</div>';
}
