<?php
/**
 * Send mail using mail() function
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Gcp\Mailer\Transport;

use Cake\Core\Configure;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Email;
use Exception;
use google\appengine\api\mail\Message;

/**
 * Send mail using mail() function
 */
class MailTransport extends AbstractTransport
{

    /**
     * Send mail
     *
     * @param \Cake\Mailer\Email $email Cake Email
     * @return array
     */
    public function send(Email $email)
    {
        $eol = PHP_EOL;
        if (isset($this->_config['eol'])) {
            $eol = $this->_config['eol'];
        }
        $headers = $email->getHeaders(['from', 'sender', 'replyTo', 'readReceipt', 'returnPath', 'to', 'cc', 'bcc']);
        $to = $headers['To'];
        unset($headers['To']);
        foreach ($headers as $key => $header) {
            $headers[$key] = str_replace(["\r", "\n"], '', $header);
        }
        $headers = $this->_headersToString($headers, $eol);
        $subject = str_replace(["\r", "\n"], '', $email->subject());
        $to = str_replace(["\r", "\n"], '', $to);

        $message = implode($eol, $email->message());

        $params = isset($this->_config['additionalParameters']) ? $this->_config['additionalParameters'] : null;
        $this->_mail($to, $subject, $message, $headers, $params);

        return ['headers' => $headers, 'message' => $message];
    }

    /**
     * Wraps internal function mail() and throws exception instead of errors if anything goes wrong
     *
     * @param string $to email's recipient
     * @param string $subject email's subject
     * @param string $message email's body
     * @param string $headers email's custom headers
     * @param string|null $params additional params for sending email
     * @throws \Cake\Network\Exception\SocketException if mail could not be sent
     * @return void
     */
    protected function _mail($to, $subject, $message, $headers, $params = null)
    {
        // GAE mail
        $headers = explode("\n", $headers);
        $from = '';
        foreach ($headers as $header) {
            list($key, $val) = explode(":", $header, 2);
            if ($key != 'From') continue;
            $from = trim($val);
            break;
        }
        try
        {
            $message = new Message([
                "sender" => $from,
                "to" => $to,
                "subject" => $subject,
                "textBody" => $message,
            ]);
            $message->send();
        }
        catch (\Exception $e)
        {
            $msg = 'Could not send email: ' . $e->getMessage();
            throw new Exception($msg);
        }
    }
}
