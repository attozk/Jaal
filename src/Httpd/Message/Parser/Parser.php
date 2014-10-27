<?php

namespace Hathoora\Jaal\Httpd\Message\Parser;

use Hathoora\Jaal\Httpd\Client\Request;

class Parser
{
    public static function parseRequest($message)
    {
        if (!$message) {
            return false;
        }

        $parts = self::parseMessage($message);

        // Parse the protocol and protocol version
        if (isset($parts['start_line'][2])) {
            $startParts = explode('/', $parts['start_line'][2]);
            $protocol = strtoupper($startParts[0]);
            $version = isset($startParts[1]) ? $startParts[1] : '1.1';
        } else {
            $protocol = 'HTTP';
            $version = '1.1';
        }

        $parsed = array(
            'method'   => strtoupper($parts['start_line'][0]),
            'protocol' => $protocol,
            'version'  => $version,
            'headers'  => $parts['headers'],
            'body'     => $parts['body']
        );

        $parsed['request_url'] = self::getUrlPartsFromMessage(isset($parts['start_line'][1]) ? $parts['start_line'][1] : '' , $parsed);
        $parsed['url'] = self::buildUrl($parsed['request_url']);

        print_r($parts);
        print_r($parsed);

        die;

        new Request($parsed['method'], $parsed['url'], $parsed['headers']);

        // EntityEnclosingRequest adds an "Expect: 100-Continue" header when using a raw request body for PUT or POST
        // requests. This factory method should accurately reflect the message, so here we are removing the Expect
        // header if one was not supplied in the message.
        if (!isset($parsed['headers']['Expect']) && !isset($parsed['headers']['expect'])) {
            $request->removeHeader('Expect');
        }

        return $request;
    }

    public function parseResponse($message)
    {
        if (!$message) {
            return false;
        }

        $parts = $this->parseMessage($message);
        list($protocol, $version) = explode('/', trim($parts['start_line'][0]));

        return array(
            'protocol'      => $protocol,
            'version'       => $version,
            'code'          => $parts['start_line'][1],
            'reason_phrase' => isset($parts['start_line'][2]) ? $parts['start_line'][2] : '',
            'headers'       => $parts['headers'],
            'body'          => $parts['body']
        );
    }

    /**
     * Parse a message into parts
     *
     * @package Guzzle
     * @param string $message Message to parse
     *
     * @return array
     */
    protected static function parseMessage($message)
    {
        $startLine = null;
        $headers = array();
        $body = '';

        // Iterate over each line in the message, accounting for line endings
        $lines = preg_split('/(\\r?\\n)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0, $totalLines = count($lines); $i < $totalLines; $i += 2) {

            $line = $lines[$i];

            // If two line breaks were encountered, then this is the end of body
            if (empty($line)) {
                if ($i < $totalLines - 1) {
                    $body = implode('', array_slice($lines, $i + 2));
                }
                break;
            }

            // Parse message headers
            if (!$startLine) {
                $startLine = explode(' ', $line, 3);
            } elseif (strpos($line, ':')) {
                $parts = explode(':', $line, 2);
                $key = trim($parts[0]);
                $value = isset($parts[1]) ? trim($parts[1]) : '';
                if (!isset($headers[$key])) {
                    $headers[$key] = $value;
                } elseif (!is_array($headers[$key])) {
                    $headers[$key] = array($headers[$key], $value);
                } else {
                    $headers[$key][] = $value;
                }
            }
        }

        return array(
            'start_line' => $startLine,
            'headers'    => $headers,
            'body'       => $body
        );
    }

    /**
     * Create URL parts from HTTP message parts
     *
     * @param string $requestUrl Associated URL
     * @param array  $parts      HTTP message parts
     *
     * @return array
     */
    protected static function getUrlPartsFromMessage($requestUrl, array $parts)
    {
        // Parse the URL information from the message
        $urlParts = array(
            'path'   => $requestUrl,
            'scheme' => 'http'
        );

        // Check for the Host header
        if (isset($parts['headers']['Host'])) {
            $urlParts['host'] = $parts['headers']['Host'];
        } elseif (isset($parts['headers']['host'])) {
            $urlParts['host'] = $parts['headers']['host'];
        } else {
            $urlParts['host'] = null;
        }

        if (false === strpos($urlParts['host'], ':')) {
            $urlParts['port'] = '';
        } else {
            $hostParts = explode(':', $urlParts['host']);
            $urlParts['host'] = trim($hostParts[0]);
            $urlParts['port'] = (int) trim($hostParts[1]);
            if ($urlParts['port'] == 443) {
                $urlParts['scheme'] = 'https';
            }
        }

        // Check if a query is present
        $path = $urlParts['path'];
        $qpos = strpos($path, '?');
        if ($qpos) {
            $urlParts['query'] = substr($path, $qpos + 1);
            $urlParts['path'] = substr($path, 0, $qpos);
        } else {
            $urlParts['query'] = '';
        }

        return $urlParts;
    }

    /**
     * Build a URL from parse_url parts. The generated URL will be a relative URL if a scheme or host are not provided.
     *
     * @param array $parts Array of parse_url parts
     *
     * @return string
     */
    public static function buildUrl(array $parts)
    {
        $url = $scheme = '';

        if (isset($parts['scheme'])) {
            $scheme = $parts['scheme'];
            $url .= $scheme . ':';
        }

        if (isset($parts['host'])) {
            $url .= '//';
            if (isset($parts['user'])) {
                $url .= $parts['user'];
                if (isset($parts['pass'])) {
                    $url .= ':' . $parts['pass'];
                }
                $url .=  '@';
            }

            $url .= $parts['host'];

            // Only include the port if it is not the default port of the scheme
            if (isset($parts['port'])
                && !(($scheme == 'http' && $parts['port'] == 80) || ($scheme == 'https' && $parts['port'] == 443))
            ) {
                $url .= ':' . $parts['port'];
            }
        }

        // Add the path component if present
        if (isset($parts['path']) && 0 !== strlen($parts['path'])) {
            // Always ensure that the path begins with '/' if set and something is before the path
            if ($url && $parts['path'][0] != '/' && substr($url, -1)  != '/') {
                $url .= '/';
            }
            $url .= $parts['path'];
        }

        // Add the query string if present
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        // Ensure that # is only added to the url if fragment contains anything.
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}