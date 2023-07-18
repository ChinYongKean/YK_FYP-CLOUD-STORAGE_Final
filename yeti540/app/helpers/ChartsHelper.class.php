<?php

namespace App\Helpers;

use App\Core\Database;

class ChartsHelper
{

    public static function getRandomColour() {
        // setup colours
        $colours = explode("|", "B02B2C|D15600|C79810|73880A|6BBA70|3F4C6B|356AA0|D01F3C");

        // choose random one
        return '#' . $colours[rand(0, COUNT($colours) - 1)];
    }

    public static function createBarChart($file, $chartType = 'last24hours') {
        // setup database
        $db = Database::getDatabase();

        $chartJS = "";
        $onLoadJS = "";
        $dataTableHTML = "";
        $dataTableHTML .= "<table class=\"table table-bordered table-striped\">\n";
        $dataTableHTML .= "<thead>\n";
        $dataTableHTML .= "    <tr>\n";

        $chartMinY = 0;
        $chartMaxY = 10;
        $yDateFormat = 'd/m/Y';
        switch ($chartType) {
            // last 24 hours chart
            case 'last24hours':
                // chart data
                $xAxisData = [];
                for ($i = 24; $i >= 0; $i--) {
                    // get data
                    $date = date("Y-m-d H:i:s", strtotime("-" . $i . " hour"));
                    $xAxisData[$date] = (int) $db->getValue("SELECT COUNT(id) AS total "
                                    . "FROM stats "
                                    . "WHERE MID(download_date, 1, 13) = '" . substr($date, 0, 13) . "' "
                                    . "AND file_id = " . (int) $file->id);

                    // prepare max for y axis on chart
                    if ($xAxisData[$date] > $chartMaxY) {
                        $chartMaxY = $xAxisData[$date];
                    }
                }
                $yDateFormat = 'H';

                // data table headers
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("time", "time")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;

            // last 7 days chart
            case 'last7days':
                // chart data
                $xAxisData = [];
                for ($i = 7; $i >= 0; $i--) {
                    // get data
                    $date = date("Y-m-d", strtotime("-" . $i . " day"));
                    $xAxisData[$date] = (int) $db->getValue("SELECT COUNT(id) AS total "
                                    . "FROM stats "
                                    . "WHERE DATE(download_date) = '" . substr($date, 0, 13) . "' "
                                    . "AND file_id = " . (int) $file->id);

                    // prepare max for y axis on chart
                    if ($xAxisData[$date] > $chartMaxY) {
                        $chartMaxY = $xAxisData[$date];
                    }
                }
                $yDateFormat = 'jS';

                // data table headers
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("date", "date")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;

            // last 30 days chart
            case 'last30days':
                // chart data
                $xAxisData = [];
                for ($i = 30; $i >= 0; $i--) {
                    // get data
                    $date = date("Y-m-d", strtotime("-" . $i . " day"));
                    $xAxisData[$date] = (int) $db->getValue("SELECT COUNT(id) AS total "
                                    . "FROM stats "
                                    . "WHERE DATE(download_date) = '" . substr($date, 0, 13) . "' "
                                    . "AND file_id = " . (int) $file->id);

                    // prepare max for y axis on chart
                    if ($xAxisData[$date] > $chartMaxY) {
                        $chartMaxY = $xAxisData[$date];
                    }
                }
                $yDateFormat = 'j';

                // data table headers
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("date", "date")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;

            // last 12 months chart
            case 'last12months':
                // chart data
                $xAxisData = [];
                for ($i = 12; $i >= 0; $i--) {
                    // get data
                    $date = date("Y-m", strtotime("-" . $i . " month"));
                    $xAxisData[$date] = (int) $db->getValue("SELECT COUNT(id) AS total "
                                    . "FROM stats "
                                    . "WHERE MID(download_date, 1, 7) = '" . substr($date, 0, 13) . "' "
                                    . "AND file_id = " . (int) $file->id);

                    // prepare max for y axis on chart
                    if ($xAxisData[$date] > $chartMaxY) {
                        $chartMaxY = $xAxisData[$date];
                    }
                }
                $yDateFormat = 'M y';

                // data table headers
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("date", "date")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;
        }

        // setup default params for chart
        $chartData = [];
        $chartData['labels'] = [];
        $chartData['datasets'] = [];
        $dataArr = [];
        $totalVisits = 0;
        foreach ($xAxisData AS $k => $total) {
            $chartData['labels'][] = CoreHelper::formatDate($k, $yDateFormat);
            $dataArr[] = $total;
            $totalVisits = $totalVisits + (int) $total;
        }
        $colour = self::getRandomColour();
        $chartData['datasets'][] = array('fillColor' => $colour, 'strokeColor' => $colour, 'data' => $dataArr);

        // prepare js code
        $chartJS .= "function respondCanvas" . $chartType . "() {\n";
        $chartJS .= "   var chartData = " . json_encode($chartData) . ";\n";
        $chartJS .= "   var chartOptions = {\n";
        $chartJS .= "       scaleOverride : true,\n";
        $chartJS .= "       scaleSteps : 10,\n";
        $chartJS .= "       scaleStepWidth : " . (ceil($chartMaxY / 10)) . ",\n";
        $chartJS .= "       scaleStartValue : " . $chartMinY . ",\n";
        $chartJS .= "       animation : false,\n";
        $chartJS .= "   };\n";
        $chartJS .= "   var c = $('#chart_" . $chartType . "');\n";
        $chartJS .= "   c.attr('width', jQuery('#chartWrapper" . $chartType . "').width());\n";
        $chartJS .= "   var height = 360;\n";
        $chartJS .= "   if(jQuery('#chartWrapper" . $chartType . "').width() < 700) {\n";
        $chartJS .= "       height = 260;\n";
        $chartJS .= "   };\n";
        $chartJS .= "   if(jQuery('#chartWrapper" . $chartType . "').width() < 460) {\n";
        $chartJS .= "       height = 160;\n";
        $chartJS .= "   };\n";
        $chartJS .= "   c.attr('height', height);\n";
        $chartJS .= "   var ct = c.get(0).getContext('2d');\n";
        $chartJS .= "   var myChart" . $chartType . " = new Chart(ct).Bar(chartData, chartOptions);\n";
        $chartJS .= "};\n";

        // onload js code
        $onLoadJS .= "respondCanvas" . $chartType . "();\n";

        // prepare data table html
        $dataTableHTML .= "    </tr>\n";
        $dataTableHTML .= "</thead>\n";
        $dataTableHTML .= "<tbody>";
        $labelsRev = array_reverse($chartData['labels'], true);
        $dataRev = array_reverse($dataArr, true);
        foreach ($labelsRev AS $k => $label) {
            $dataTableHTML .= "<tr>";
            $dataTableHTML .= "<td class=\"center\">" . $label . "</td>";
            $dataTableHTML .= "<td class=\"center\">" . $dataRev[$k] . "</td>";
            $percentage = 0;
            if ($totalVisits > 0) {
                $percentage = ($dataRev[$k] / $totalVisits) * 100;
            }
            $dataTableHTML .= "<td class=\"center mobileHide\">" . number_format($percentage, 1) . "%</td>";
            $dataTableHTML .= "</tr>";
        }
        $dataTableHTML .= "</tbody>\n";
        $dataTableHTML .= "</table>";

        // prepare initial canvas html
        $canvasHTML = '<div class="chartWrapper" id="chartWrapper' . $chartType . '"><canvas id="chart_' . $chartType . '"></canvas></div><br/>';

        return array('chartJS' => $chartJS, 'onLoadJS' => $onLoadJS, 'dataTableHTML' => $dataTableHTML, 'canvasHTML' => $canvasHTML);
    }

    public static function createPieChart($shortUrlObj, $chartType = 'countries') {
        // setup database
        $db = Database::getDatabase();

        $chartJS = "";
        $onLoadJS = "";
        $dataTableHTML = "";
        $dataTableHTML .= "<table class=\"table table-bordered table-striped\">\n";
        $dataTableHTML .= "<thead>\n";
        $dataTableHTML .= "    <tr>\n";

        $translateData = true;
        switch ($chartType) {
            // top countries pie
            case 'countries':
                // chart data
                $xAxisData = $db->getRows("SELECT country AS label, COUNT(id) AS total "
                        . "FROM stats "
                        . "WHERE file_id = " . (int) $shortUrlObj->id . " "
                        . "GROUP BY country "
                        . "ORDER BY total DESC");

                // data table headers
                $dataTableHTML .= "       <th class=\"col-md-4\">" . UCWords(TranslateHelper::t("country", "country")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;

            // top referrers pie
            case 'referrers':
                // chart data
                $xAxisData = $db->getRows("SELECT base_url AS label, COUNT(id) AS total "
                        . "FROM stats "
                        . "WHERE file_id = " . (int) $shortUrlObj->id . " "
                        . "GROUP BY base_url "
                        . "ORDER BY total DESC "
                        . "LIMIT 20");

                // data table headers
                $dataTableHTML .= "       <th class=\"col-md-4\">" . UCWords(TranslateHelper::t("site", "site")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                $translateData = false;
                break;

            // top browsers pie
            case 'browsers':
                // chart data
                $xAxisData = $db->getRows("SELECT browser_family AS label, COUNT(id) AS total "
                        . "FROM stats "
                        . "WHERE file_id = " . (int) $shortUrlObj->id . " "
                        . "GROUP BY browser_family "
                        . "ORDER BY total DESC "
                        . "LIMIT 20");

                // data table headers
                $dataTableHTML .= "       <th class=\"col-md-4\">" . UCWords(TranslateHelper::t("browser", "browser")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;

            // top os pie
            case 'os':
                // chart data
                $xAxisData = $db->getRows("SELECT os AS label, COUNT(id) AS total "
                        . "FROM stats "
                        . "WHERE file_id = " . (int) $shortUrlObj->id . " "
                        . "GROUP BY os "
                        . "ORDER BY total DESC "
                        . "LIMIT 20");

                // data table headers
                $dataTableHTML .= "       <th class=\"col-md-4\">" . UCWords(TranslateHelper::t("operating_system", "operating_system")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4\">" . UCWords(TranslateHelper::t("total_visits")) . "</th>\n";
                $dataTableHTML .= "       <th class=\"center col-md-4 mobileHide\">" . UCWords(TranslateHelper::t("percentage")) . "</th>\n";
                break;
        }

        // setup default params for chart
        $chartData = [];
        $dataArr = [];
        $totalVisits = 0;
        foreach ($xAxisData AS $row) {
            $labelOrg = strip_tags($row['label']);

            $label = $labelOrg;
            if ($translateData == true) {
                $label = strip_tags(TranslateHelper::t($labelOrg, $labelOrg));
            }

            if ($chartType == 'referrers') {
                if (strlen($label) == 0) {
                    $label = 'direct';
                }
            }
            elseif (strlen($label) == 0) {
                $label = 'unknown';
            }
            $chartData[] = array('label' => '    ' . $label . '    ', 'value' => (int) $row['total'], 'color' => self::getRandomColour(), 'labelColor' => 'white');
            $totalVisits = $totalVisits + (int) $row['total'];
        }

        // prepare js code
        $chartJS .= "function respondCanvas" . $chartType . "() {\n";
        $chartJS .= "   var chartData = " . json_encode($chartData) . ";\n";
        $chartJS .= "   var chartOptions = {\n";
        $chartJS .= "       labelAlign: 'left', 'labelFontSize':10,\n";
        $chartJS .= "       animation : false\n";
        $chartJS .= "   };\n";

        $chartJS .= "   var c = $('#chart_" . $chartType . "');\n";
        $chartJS .= "   var height = 360;\n";
        $chartJS .= "   if((jQuery(document).width() < 460)) {\n";
        $chartJS .= "       height = 200;\n";
        $chartJS .= "   }\n";
        $chartJS .= "   c.attr('width', height);\n";
        $chartJS .= "   c.attr('height', height);\n";
        $chartJS .= "   var ct = c.get(0).getContext('2d');\n";
        $chartJS .= "   var myChart" . $chartType . " = new Chart(ct).Pie(chartData, chartOptions);\n";
        $chartJS .= "};\n";

        // onload js code
        $onLoadJS .= "if(typeof(respondCanvas" . $chartType . ") === 'function') {\n";
        $onLoadJS .= "  respondCanvas" . $chartType . "();\n";
        $onLoadJS .= "};\n";

        // prepare initial canvas html
        $canvasHTML = '<div class="chartWrapper pieWrapper" id="chartWrapper' . $chartType . '"><canvas id="chart_' . $chartType . '"></canvas></div><br/>';

        // prepare data table html
        $dataTableHTML .= "    </tr>\n";
        $dataTableHTML .= "</thead>\n";
        $dataTableHTML .= "<tbody>";
        if (count($xAxisData)) {
            foreach ($xAxisData AS $row) {
                $labelOrg = strip_tags($row['label']);
                $label = $labelOrg ? $labelOrg : "unknown";
                $imagePath = null;
                switch ($chartType) {
                    // top countries pie
                    case 'countries':
                        $imagePath = SITE_IMAGE_PATH . "/stats/flags/" . strtolower($label) . ".png";
                        $label = TranslateHelper::t($label, $label);
                        break;
                    // top referrers pie
                    case 'referrers':
                        $baseUrl = $labelOrg ? $labelOrg : "direct";
                        $label = $baseUrl;
                        if ($dataRow['label']) {
                            $label = "<a href='http://" . $baseUrl . "' target='_blank'>" . $baseUrl . "</a>";
                        }
                        break;
                    // top browsers pie
                    case 'browsers':
                        $imagePath = SITE_IMAGE_PATH . "/stats/browsers/" . strtolower($label) . ".png";
                        $label = TranslateHelper::t($label, $label);
                        break;
                    // top os pie
                    case 'os':
                        $imagePath = SITE_IMAGE_PATH . "/stats/os/" . strtolower($label) . ".png";
                        $label = TranslateHelper::t($label, $label);
                        break;
                }

                $dataTableHTML .= "<tr>";
                $dataTableHTML .= "<td>";
                if ($imagePath) {
                    $dataTableHTML .= "<img src=\"" . $imagePath . "\" width='16' alt=\"" . $label . "\" style=\"vertical-align: middle;\">&nbsp;&nbsp;";
                }
                $dataTableHTML .= $label;
                $dataTableHTML .= "</td>";
                $dataTableHTML .= "<td class=\"center\">" . $row['total'] . "</td>";
                $dataTableHTML .= "<td class=\"center mobileHide\">" . number_format(($row['total'] / $totalVisits) * 100, 1) . "%</td>";
                $dataTableHTML .= "</tr>";
            }
        }
        else {
            $dataTableHTML .= "<tr><td colspan='3'>" . TranslateHelper::t('no_data', 'No data') . "</td></tr>";
            $chartJS = '';
            $canvasHTML = '';
        }
        $dataTableHTML .= "</tbody>\n";
        $dataTableHTML .= "</table>";

        return array('chartJS' => $chartJS, 'onLoadJS' => $onLoadJS, 'dataTableHTML' => $dataTableHTML, 'canvasHTML' => $canvasHTML);
    }

}
