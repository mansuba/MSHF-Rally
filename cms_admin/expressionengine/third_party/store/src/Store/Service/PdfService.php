<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Service;

use DOMPDF;

class PdfService extends AbstractService
{
    public function create_pdf()
    {
        $this->initialize();

        $paper = config_item('store_export_pdf_page_format');
        $orientation = config_item('store_export_pdf_orientation') == 'L' ? 'landscape' : 'portrait';

        $dompdf = new DOMPDF;
        $dompdf->set_paper($paper, $orientation);

        return $dompdf;
    }

    protected function initialize()
    {
        if ( ! defined('DOMPDF_ENABLE_AUTOLOAD')) {
            define('DOMPDF_ENABLE_AUTOLOAD', false);
            define('DOMPDF_LOG_OUTPUT_FILE', false);

            require(PATH_THIRD.'store/vendor/dompdf/dompdf/dompdf_config.inc.php');
        }
    }
}
