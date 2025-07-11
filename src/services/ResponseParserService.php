<?php
namespace eseperio\verifactu\services;

use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\QueryResponse;
use eseperio\verifactu\dictionaries\ErrorRegistry;

/**
 * Service responsible for parsing AEAT SOAP/XML responses into
 * strongly-typed PHP model objects and mapping errors.
 */
class ResponseParserService
{
    /**
     * Parses an XML response string from AEAT for invoice registration/cancellation.
     *
     * @param string $xmlResponse
     * @return InvoiceResponse
     */
    public static function parseInvoiceResponse($xmlResponse)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlResponse);

        // Map DOM to model
        $model = new InvoiceResponse();

        // Example: you can use DOMXPath or SimpleXML for real field mapping
        $xpath = new \DOMXPath($doc);

        // CSV (if present)
        $csvNode = $xpath->query('//CSV')->item(0);
        if ($csvNode) {
            $model->csv = $csvNode->nodeValue;
        }

        // Header
        $headerNode = $xpath->query('//Cabecera')->item(0);
        if ($headerNode) {
            $model->header = self::xmlNodeToArray($headerNode);
        }

        // Wait time (TiempoEsperaEnvio)
        $waitNode = $xpath->query('//TiempoEsperaEnvio')->item(0);
        if ($waitNode) {
            $model->waitTime = $waitNode->nodeValue;
        }

        // Submission status (EstadoEnvio)
        $statusNode = $xpath->query('//EstadoEnvio')->item(0);
        if ($statusNode) {
            $model->submissionStatus = $statusNode->nodeValue;
        }

        // SubmissionData
        $dataNode = $xpath->query('//DatosPresentacion')->item(0);
        if ($dataNode) {
            $model->submissionData = self::xmlNodeToArray($dataNode);
        }

        // Line responses (RespuestaLinea)
        $model->lineResponses = [];
        foreach ($xpath->query('//RespuestaLinea') as $lineNode) {
            $line = self::xmlNodeToArray($lineNode);
            // Map AEAT error codes to human-readable messages
            if (isset($line['CodigoErrorRegistro'])) {
                $line['ErrorDescription'] = ErrorRegistry::getErrorMessage($line['CodigoErrorRegistro']);
            }
            $model->lineResponses[] = $line;
        }

        return $model;
    }

    /**
     * Parses an XML response string from AEAT for queries.
     *
     * @param string $xmlResponse
     * @return QueryResponse
     */
    public static function parseQueryResponse($xmlResponse)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlResponse);

        $model = new QueryResponse();
        $xpath = new \DOMXPath($doc);

        $headerNode = $xpath->query('//Cabecera')->item(0);
        if ($headerNode) {
            $model->header = self::xmlNodeToArray($headerNode);
        }

        $periodNode = $xpath->query('//PeriodoImputacion')->item(0);
        if ($periodNode) {
            $model->period = self::xmlNodeToArray($periodNode);
        }

        $model->paginationIndicator = self::findText($xpath, '//IndicadorPaginacion');
        $model->queryResult = self::findText($xpath, '//ResultadoConsulta');

        $model->foundRecords = [];
        foreach ($xpath->query('//RegistroRespuestaConsultaFactuSistemaFacturacion') as $recordNode) {
            $model->foundRecords[] = self::xmlNodeToArray($recordNode);
        }

        $paginationKeyNode = $xpath->query('//ClavePaginacion')->item(0);
        if ($paginationKeyNode) {
            $model->paginationKey = self::xmlNodeToArray($paginationKeyNode);
        }

        return $model;
    }

    /**
     * Converts a DOMNode and its children to a PHP associative array.
     *
     * @param \DOMNode $node
     * @return array|string
     */
    protected static function xmlNodeToArray($node)
    {
        $result = [];
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $result[$child->nodeName] = self::xmlNodeToArray($child);
                } elseif ($child->nodeType === XML_TEXT_NODE) {
                    return trim($child->nodeValue);
                }
            }
        }
        return $result;
    }

    /**
     * Helper for quickly fetching text content of a single XML node via XPath.
     *
     * @param \DOMXPath $xpath
     * @param string $query
     * @return string|null
     */
    protected static function findText($xpath, $query)
    {
        $node = $xpath->query($query)->item(0);
        return $node ? trim($node->nodeValue) : null;
    }
}
