<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mail;
use Zend\Mvc\Controller\AbstractRestfulController;
use ReCaptcha\ReCaptcha;
use ZF\ApiProblem\ApiProblemResponse;
use ZF\ApiProblem\ApiProblem;

class FeedbackController extends AbstractRestfulController
{
    /**
     * @var InputFilter
     */
    private $postInputFilter;

    private $transport;

    /**
     * @var array
     */
    private $options;

    /**
     * @var array
     */
    private $recaptcha;

    public function __construct(InputFilter $postInputFilter, $transport, array $options, array $recaptcha)
    {
        $this->postInputFilter = $postInputFilter;
        $this->transport = $transport;
        $this->options = $options;
        $this->recaptcha = $recaptcha;
    }

    public function postAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();


        $recaptcha = new ReCaptcha($this->recaptcha['privateKey']);

        $captchaResponse = null;
        if (isset($data['captcha'])) {
            $captchaResponse = $data['captcha'];
        }

        $result = $recaptcha->verify($captchaResponse, $this->getRequest()->getServer('REMOTE_ADDR'));

        if (! $result->isSuccess()) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'captcha' => 'Captcha is invalid'
                    ]
                ])
            );
        }


        $message = sprintf(
            "Имя: %s\nE-mail: %s\nСообщение:\n%s",
            $data['name'],
            $data['email'],
            $data['message']
        );

        $mail = new Mail\Message();
        $mail
            ->setEncoding('utf-8')
            ->setBody($message)
            ->setFrom($this->options['from'], $this->options['fromname'])
            ->addTo($this->options['to'])
            ->setSubject($this->options['subject']);

        if ($data['email']) {
            $mail->setReplyTo($data['email'], $data['name']);
        }

        $this->transport->send($mail);

        return $this->getResponse()->setStatusCode(200);
    }
}
