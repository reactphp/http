<?php

namespace React\Http;

use React\Socket\ConnectionInterface;

class RequestHeaderParserFactory implements RequestHeaderParserFactoryInterface
{

    /**
     * @param ConnectionInterface $conn
     * @return RequestHeaderParserInterface
     */
    public function create(ConnectionInterface $conn)
    {
        $uriLocal = $this->getUriLocal($conn);
        $uriRemote = $this->getUriRemote($conn);

        return new RequestHeaderParser($uriLocal, $uriRemote);
    }

    /**
     * @param ConnectionInterface $conn
     * @return string
     */
    private function getUriLocal(ConnectionInterface $conn)
    {
        $uriLocal = $conn->getLocalAddress();
        if ($uriLocal !== null && strpos($uriLocal, '://') === false) {
            // local URI known but does not contain a scheme. Should only happen for old Socket < 0.8
            // try to detect transport encryption and assume default application scheme
            $uriLocal = ($this->isConnectionEncrypted($conn) ? 'https://' : 'http://') . $uriLocal;
        } elseif ($uriLocal !== null) {
            // local URI known, so translate transport scheme to application scheme
            $uriLocal = strtr($uriLocal, ['tcp://' => 'http://', 'tls://' => 'https://']);
        }

        return $uriLocal;
    }

    /**
     * @param ConnectionInterface $conn
     * @return string
     */
    private function getUriRemote(ConnectionInterface $conn)
    {
        $uriRemote = $conn->getRemoteAddress();
        if ($uriRemote !== null && strpos($uriRemote, '://') === false) {
            // local URI known but does not contain a scheme. Should only happen for old Socket < 0.8
            // actual scheme is not evaluated but required for parsing URI
            $uriRemote = 'unused://' . $uriRemote;
        }

        return $uriRemote;
    }

    /**
     * @param ConnectionInterface $conn
     * @return bool
     * @codeCoverageIgnore
     */
    private function isConnectionEncrypted(ConnectionInterface $conn)
    {
        // Legacy PHP < 7 does not offer any direct access to check crypto parameters
        // We work around by accessing the context options and assume that only
        // secure connections *SHOULD* set the "ssl" context options by default.
        if (PHP_VERSION_ID < 70000) {
            $context = isset($conn->stream) ? stream_context_get_options($conn->stream) : array();

            return (isset($context['ssl']) && $context['ssl']);
        }

        // Modern PHP 7+ offers more reliable access to check crypto parameters
        // by checking stream crypto meta data that is only then made available.
        $meta = isset($conn->stream) ? stream_get_meta_data($conn->stream) : array();

        return (isset($meta['crypto']) && $meta['crypto']);
    }

}
