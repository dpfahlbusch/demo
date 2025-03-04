<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace App\Services;

use App\Model\Customer;
use App\Model\CustomerManagementFramework\PasswordRecoveryInterface;
use CustomerManagementFrameworkBundle\CustomerProvider\CustomerProviderInterface;
use CustomerManagementFrameworkBundle\Model\CustomerInterface;
use Pimcore\Mail;
use Pimcore\Model\Document\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NewsletterDoubleOptInService
{
    /**
     * NewsletterDoubleOptInService constructor.
     */
    public function __construct(
        protected UrlGeneratorInterface $urlGenerator,
        protected CustomerProviderInterface $customerProvider
    ) {
    }

    /**
     * @throws \Exception
     */
    public function sendDoubleOptInMail(Customer $customer, Email $emailDocument): void
    {
        if (!$customer->getNewsletterConfirmed()) {
            $token = md5($customer->getId() . time() . uniqid());
            $customer->setNewsletterConfirmToken($token);
            $customer->save();

            //send mail
            $mail = new Mail();
            $mail->setDocument($emailDocument);
            $mail->setParams([
                'customer' => $customer,
                'customerId' => $customer->getId(),
                'token' => $token,
                'tokenLink' => $this->urlGenerator->generate('account-confirm-newsletter', ['token' => $token]),
            ]);
            $mail->addTo($customer->getEmail());

            $mail->send();
        }
    }

    public function getCustomerByToken(string $token): ?CustomerInterface
    {
        $customerList = $this->customerProvider->getList();
        $customerList->setCondition('newsletterConfirmToken = ?', [$token]);
        $customerList->setLimit(1);

        return $customerList->current();
    }

    public function handleDoubleOptInConfirmation(string $token): ?CustomerInterface
    {
        $customer = $this->getCustomerByToken($token);
        if ($customer) {
            $customer->setNewsletterConfirmed(true);
            $customer->save();

            return $customer;
        }

        return null;
    }
}
