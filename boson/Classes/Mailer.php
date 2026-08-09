<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2025 All rights reserved
* @version   2.1
*
* Обёртка над PHPMailer с поддержкой шаблонов (Smarty/Native).
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

class Mailer
{
    protected $phpmail           = null;
    protected $settings          = null;
    protected $ui                = null;
    protected $templateExtension = '.tpl';
    protected $engineType        = 'smarty';

    /**
     * Constructor
     * @throws Exception
     */
    public function __construct()
    {
        $this->initializeDirectories();
        $this->loadSettings();
        $this->initializeMailer();
        $this->initializeTemplateEngine();
        $this->configureMailer();
    }

    /**
     * Initialize required directories
     */
    protected function initializeDirectories()
    {
        $mailsPath = APP_DIR . DIR_SEP . 'mails';
        
        if( !is_dir($mailsPath) ) {
            @mkdir($mailsPath, 0777, true);
        }

        $compileDir = SMARTY_TEMP_DIR . DIR_SEP . 'mails';
        $cacheDir   = $compileDir . DIR_SEP . 'cache';
        
        foreach([$compileDir, $cacheDir] as $dir) {
            if( !is_dir($dir) ) {
                @mkdir($dir, 0777, true);
            }
        }
        
        return $this;
    }

    /**
     * Load mailer settings
     * @throws Exception
     */
    protected function loadSettings( $reload = false )
    {
        if( empty($this->settings) || $reload ) {
            $this->settings = cfg('mailer');
            
            if( empty($this->settings->email_from) ) {
                throw new Exception('Mailer configuration not found or incomplete.');
            }
        }
        
        return $this;
    }

    /**
     * Initialize PHPMailer instance
     */
    protected function initializeMailer()
    {
        if( empty($this->phpmail) ) {
            $this->phpmail = new PHPMailer(true);
            $this->phpmail->CharSet = 'utf-8';
        }
        
        return $this;
    }

    /**
     * Initialize template engine
     */
    protected function initializeTemplateEngine()
    {
        if( empty($this->ui) ) {
            $config = cfg('config');
            
            if( !empty($config->cover) && $config->cover == 'smarty' && class_exists('\Smarty\Smarty') ) {
                $this->engineType = 'smarty';
                $this->templateExtension = '.tpl';

                $this->ui = new \Smarty\Smarty();

                $this->ui->setTemplateDir(APP_DIR . DIR_SEP . 'mails');
                $this->ui->setCompileDir(SMARTY_TEMP_DIR . DIR_SEP . 'mails');
                $this->ui->setCacheDir(SMARTY_TEMP_DIR . DIR_SEP . 'mails' . DIR_SEP . 'cache');
                
                $this->ui->registerPlugin('function', 'i18n', 'smarty_function_i18n');
                $this->ui->registerPlugin('function', 'num2word', 'smarty_function_num2word');
                
            } else {
                $this->engineType = 'native';
                $this->templateExtension = '.phtml';

                $this->ui = new \Boson\Native(APP_DIR . DIR_SEP . 'mails');
            }
        }
        
        return $this;
    }

    /**
     * Configure PHPMailer with settings
     */
    protected function configureMailer()
    {
        $this->phpmail->setFrom(
            $this->settings->email_from,
            $this->settings->from_name ?? ''
        );

        if( !empty($this->settings->replyto) ) {
            $this->phpmail->addReplyTo($this->settings->replyto);
        }

        $this->configureTransport();
        
        return $this;
    }

    /**
     * Configure mail transport method
     */
    protected function configureTransport()
    {
        $type = $this->settings->type ?? 'mail';

        switch($type) {
            case 'smtp':
                $this->configureSmtp();
                break;
                
            case 'sendmail':
                $this->configureSendmail();
                break;
                
            case 'qmail':
                $this->phpmail->isQmail();
                break;
                
            default:
                $this->phpmail->isMail();
        }
        
        return $this;
    }

    /**
     * Configure SMTP settings
     */
    protected function configureSmtp()
    {
        $this->phpmail->isSMTP();
        
        if( !empty($this->settings->host) ) {
            $this->phpmail->Host = $this->settings->host;
        }
        
        if( !empty($this->settings->port) ) {
            $this->phpmail->Port = (int)$this->settings->port;
        }
        
        if( !empty($this->settings->username) ) {
            $this->phpmail->Username = $this->settings->username;
            
            if( !empty($this->settings->password) ) {
                $this->phpmail->Password = $this->settings->password;
                $this->phpmail->SMTPAuth = true;
            }
        }
        
        if( !empty($this->settings->authtype) ) {
            $this->phpmail->AuthType = $this->settings->authtype;
        }
        
        return $this;
    }

    /**
     * Configure Sendmail settings
     */
    protected function configureSendmail()
    {
        $this->phpmail->isSendmail();
        
        if( !empty($this->settings->sendmail) ) {
            $this->phpmail->Sendmail = (string)$this->settings->sendmail;
        }
        
        return $this;
    }

    /**
     * Set sender address
     */
    public function from(string $address, string $name = '', bool $auto = true): self
    {
        $this->phpmail->setFrom($address, $name, $auto);
        
        return $this;
    }

    /**
     * Set mail subject
     */
    public function subject(string $subject): self
    {
        $this->phpmail->Subject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        
        return $this;
    }

    /**
     * Add recipient
     */
    public function to(string $address, string $name = ''): self
    {
        $this->phpmail->addAddress($address, $name);
        
        return $this;
    }

    /**
     * Add carbon copy recipient
     */
    public function cc(string $address, string $name = ''): self
    {
        $this->phpmail->addCC($address, $name);
        
        return $this;
    }

    /**
     * Add blind carbon copy recipient
     */
    public function bcc(string $address, string $name = ''): self
    {
        $this->phpmail->addBCC($address, $name);
        
        return $this;
    }

    /**
     * Attach file to mail
     */
    public function attach(
        string $path, 
        string $name = '', 
        string $encoding = 'base64', 
        string $type = '', 
        string $disposition = 'attachment'
    ): self {
        $this->phpmail->addAttachment($path, $name, $encoding, $type, $disposition);
        
        return $this;
    }

    /**
     * Assign variable to template
     */
    public function assign($tpl_var, $value = null): self
    {
        $this->ui->assign($tpl_var, $value);
        
        return $this;
    }

    /**
     * Fetch template content
     */
    public function fetch(string $template): string
    {
        if( $this->engineType === 'smarty' && !str_ends_with($template, '.tpl') ) {
            $template .= '.tpl';
        }

        return $this->ui->fetch($template);
    }

    /**
     * Send mail using template
     */
    public function send(string $template, ?array $values = null): bool
    {
        if( $this->engineType === 'smarty' && !str_ends_with($template, '.tpl') ) {
            $template .= '.tpl';
        }

        if( !empty($values) ) {
            $this->ui->assign($values);
        }

        $this->phpmail->msgHTML(
            $this->ui->fetch($template)
        );
        
        return $this->sendMail();
    }

    /**
     * Send HTML mail directly
     */
    public function sendHTML(string $html): bool
    {
        $this->phpmail->msgHTML($html);
        
        return $this->sendMail();
    }

    /**
     * Send the actual email
     * @throws Exception
     */
    protected function sendMail(): bool
    {
        try {
            return $this->phpmail->send();
            
        } catch(PHPMailerException $e) {
            error_log('Mailer Error: ' . $e->getMessage());
            
            throw new Exception('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * Clear all recipients and attachments (для повторного использования)
     */
    public function clear(): self
    {
        $this->phpmail->clearAllRecipients();
        $this->phpmail->clearAttachments();
        
        return $this;
    }

    /**
     * Полный сброс — очищает всё, включая тему и отправителя
     */
    public function reset(): self
    {
        $this->phpmail->clearAllRecipients();
        $this->phpmail->clearAttachments();
        $this->phpmail->clearCustomHeaders();
        $this->phpmail->Subject = '';
        $this->phpmail->Body    = '';
        $this->phpmail->AltBody = '';
        
        // Переприменяем from из конфига
        $this->configureMailer();
        
        return $this;
    }

    /**
     * Get PHPMailer instance for advanced configuration
     */
    public function getMailer(): PHPMailer
    {
        return $this->phpmail;
    }

    /**
     * Get template engine instance
     */
    public function getTemplateEngine()
    {
        return $this->ui;
    }

    /**
     * Magic method to call PHPMailer methods directly
     * @throws Exception
     */
    public function __call(string $name, array $args)
    {
        if( method_exists($this->phpmail, $name) ) {
            return $this->phpmail->$name(...$args);
        }
        
        throw new Exception("Call to undefined method {$name}");
    }
}
