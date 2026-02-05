<?php

class LTI
{

    public function __construct()
    {
        include 'php/message.php';
        include 'php/OAuthBody.php';
    }

    /**
     * Parse out the necessary fields from BlackBoard LTI post
     */
    public function getDataFromPost($data)
    {
        $returnObject = json_decode("");

        try {
            $returnObject->url     = $data["lis_outcome_service_url"];
            $returnObject->id      = $data["lis_result_sourcedid"];
            $returnObject->isError = false;
        } catch (Exception $e) {
            // var_dump($e);
            $returnObject->isError = true;
        }

        return $returnObject;
    }

    /**
     * Send the grade to BlackBoard
     */
    public function sendGrade($url, $id, $grade)
    {
        return sendOAuthBodyPOST(
            "POST",
            $url,
            "anythingKey",
            "anythingSecret",
            "application/xml",
            message($id, $grade)
        );
    }

    /**
     * Used in tandem with sendGrade(), determines whether the response of sendGrade() was successful.
     * The result of sendGrade() is of XML type.
     *
     * @return bool - true on success, false on error/failed sendGrade()
     */
    public function isSuccessful($rawXML)
    {
        // Parse the XML
        if (!($xml = simplexml_load_string($rawXML))) {
            return false;
        }

        /**
         * Check if the necessary fields exist
         */

        if (!property_exists($xml, "imsx_POXHeader")) {
            return false;
        }

        if (!property_exists($xml->imsx_POXHeader, "imsx_POXResponseHeaderInfo")) {
            return false;
        }

        if (!property_exists($xml->imsx_POXHeader->imsx_POXResponseHeaderInfo, "imsx_statusInfo")) {
            return false;
        }

        if (!property_exists($xml->imsx_POXHeader->imsx_POXResponseHeaderInfo->imsx_statusInfo, "imsx_codeMajor")) {
            return false;
        }

        if ($xml->imsx_POXHeader->imsx_POXResponseHeaderInfo->imsx_statusInfo->imsx_codeMajor != "success") {
            return false;
        }

        return true;
    }
}
