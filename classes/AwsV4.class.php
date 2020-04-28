<?php

/**
 * Copyright 2019 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace Astore;


/**
 * Amazon-provided class to create authentication for requests.
 * @package astore.
 */
class AwsV4
{
    /** Access key value.
     * @var string */
    private $accessKeyID = null;

    /** Secret key value.
     * @var string */
    private $secretAccessKey = null;

    /** URL path for request.
     * @var string */
    private $path = null;

    /** Amazon Region name, e.g. `us-east-1`.
     * @var string */
    private $regionName = null;

    /** Service name, e.g. `www.amazon.com`.
     * @var string */
    private $serviceName = null;

    /** HTTP Request Method, e.g. `POST`.
     * @var string */
    private $httpMethodName = null;

    /** Query Parameters.
     * @deprecated
     * @var array */
    private $queryParametes = array ();

    /** Request headers.
     * @var array */
    private $awsHeaders = array ();

    /** Payload, a JSON-encoded array.
     * @var string */
    private $payload = "";

    /** Hashing algorithm.
     * @var string */
    private $HMACAlgorithm = "AWS4-HMAC-SHA256";

    /** Static request string include in the hashing.
     * @var string */
    private $aws4Request = "aws4_request";

    /** Signed header string.
     * @var string */
    private $strSignedHeader = null;

    /** Request timestamp.
     * @var string */
    private $xAmzDate = null;

    /** Current date.
     * @var string */
    private $currentDate = null;


    /**
     * Create the object and assign known values.
     *
     * @param   string  $access_key     Amazon-assigned access key
     * @param   string  $secret_key     Amazon-assigned secret key
     */
    public function __construct($access_key, $secret_key)
    {
        $this->accessKeyID = $access_key;
        $this->secretAccessKey = $secret_key;
        $this->xAmzDate = $this->getTimeStamp ();
        $this->currentDate = $this->getDate ();
    }

    /**
     * Set the request path.
     *
     * @param   string  $path   Path to set
     * @return  object  $this
     */
    function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set the service name.
     *
     * @param   string  $serviceName    Service name
     * @return  object  $this
     */
    function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    /**
     * Set the Amazon region ID.
     *
     * @param   string  $regoinName Region name
     * @return  object  $this
     */
    function setRegionName($regionName)
    {
        $this->regionName = $regionName;
        return $this;
    }

    /**
     * Set the request payload to submit.
     *
     * @param   string  $payload    JSON-encoded string
     * @return  object  $this
     */
    function setPayload($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Set the request method, typically POST.
     *
     * @param   string  $method     Request method
     * @return  object  $this
     */
    function setRequestMethod($method)
    {
        $this->httpMethodName = $method;
        return $this;
    }

    /**
     * Add a header value.
     *
     * @param   string  $headerName     Name of header value
     * @param   string  $headerValue    Header value
     * @return  object  $this
     */
    function addHeader($headerName, $headerValue)
    {
        $this->awsHeaders [$headerName] = $headerValue;
        return $this;
    }

    /**
     * Prepare the request and get the canonical URL.
     *
     * @return  string      Canonical URL
     */
    private function prepareCanonicalRequest()
    {
        $canonicalURL = "";
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . "\n";
        $signedHeaders = '';
        foreach ( $this->awsHeaders as $key => $value ) {
            $signedHeaders .= $key . ";";
            $canonicalURL .= $key . ":" . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr ( $signedHeaders, 0, - 1 );
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex ( $this->payload );
        return $canonicalURL;
    }

    /**
     * Prepare the string to be signed for authentication.
     *
     * @param   string  $canonicalURL   Canonical URL
     * @return  string          String to be signed.
     */
    private function prepareStringToSign($canonicalURL)
    {
        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex ( $canonicalURL );
        return $stringToSign;
    }

    /**
     * Calculate the signature for the string to be signed.
     *
     * @param   string  $stringToSign   String to be signed
     * @return  string      Signature
     */
    private function calculateSignature($stringToSign)
    {
        $signatureKey = $this->getSignatureKey ( $this->secretAccessKey, $this->currentDate, $this->regionName, $this->serviceName );
        $signature = hash_hmac ( "sha256", $stringToSign, $signatureKey, true );
        $strHexSignature = strtolower ( bin2hex ( $signature ) );
        return $strHexSignature;
    }

    /**
     * Get all the headers, including the authorizatoin header.
     *
     * @return  array   Array of header key=>value pairs
     */
    public function getHeaders()
    {
        $this->awsHeaders ['x-amz-date'] = $this->xAmzDate;
        ksort ( $this->awsHeaders );
        $canonicalURL = $this->prepareCanonicalRequest ();
        $stringToSign = $this->prepareStringToSign ( $canonicalURL );
        $signature = $this->calculateSignature ( $stringToSign );
        if ($signature) {
            $this->awsHeaders ['Authorization'] = $this->buildAuthorizationString ( $signature );
        }
        return $this->awsHeaders;
    }

    /**
     * Create the authorization string to be set in the headers.
     *
     * @param   string  $strSignature   Signature string
     * @return  string      Authorization string for the header.
     */
    private function buildAuthorizationString($strSignature)
    {
        return $this->HMACAlgorithm . " " . 
            "Credential=" . $this->accessKeyID . "/" .
            $this->getDate () . "/" .
            $this->regionName . "/" .
            $this->serviceName . "/" .
            $this->aws4Request . "," .
            "SignedHeaders=" . $this->strSignedHeader .
            "," . "Signature=" . $strSignature;
    }

    /**
     * Create a hex value of some data.
     *
     * @param   string  $data   Data to encode
     * @return  string      Hex data string
     */
    private function generateHex($data)
    {
        return strtolower ( bin2hex ( hash ( "sha256", $data, true ) ) );
    }

    /**
     * Get the signature key.
     *
     * @param   string  $key    Amazon secret key
     * @param   string  $date   Current date
     * @param   string  $reginoName Amazon region ID
     * @param   string  $serviceName    Amazon service name
     * @return  string      Signature key
     */
    private function getSignatureKey($key, $date, $regionName, $serviceName)
    {
        $kSecret = "AWS4" . $key;
        $kDate = hash_hmac ( "sha256", $date, $kSecret, true );
        $kRegion = hash_hmac ( "sha256", $regionName, $kDate, true );
        $kService = hash_hmac ( "sha256", $serviceName, $kRegion, true );
        $kSigning = hash_hmac ( "sha256", $this->aws4Request, $kService, true );

        return $kSigning;
    }

    /**
     * Get the current timestamp.
     *
     * @return  string      Timestamp string
     */
    private function getTimeStamp()
    {
        return gmdate ( "Ymd\THis\Z" );
    }

    /**
     * Get the current date.
     *
     * @return  string      Current date as YYYYMMDD
     */
    private function getDate()
    {
        return gmdate ( "Ymd" );
    }

}
?>
