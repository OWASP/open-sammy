<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Mailing;
use App\Entity\MailTemplate;
use App\Entity\User;
use App\Enum\MailTemplateType;
use App\Enum\Role;
use App\Repository\MailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

class MailingService
{
    private const MAILING_TRANSLATION_FILENAME = 'mailTemplates+intl-icu.en.yaml';
    public const MAX_MAILS_TO_PROCESS = 100;
    public const SENDING_RATE_LIMITS_PER_SECOND = 14; // per

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly MailTemplateRepository $mailTemplateRepository,
        private readonly SanitizerService $sanitizerService
    ) {
    }

    public function add(MailTemplateType $mailTemplateType, User $user, array $extraPlaceholders = [], string $attachment = ''): void
    {
        $mailTemplateEntity = $this->resolveTemplate($mailTemplateType);
        $this->addCustom($user, $mailTemplateEntity, $extraPlaceholders, $attachment);
    }

    private function resolveTemplate(MailTemplateType $mailTemplateType): ?MailTemplate
    {
        $mailTemplateEntity = $this->mailTemplateRepository->findOneBy(['type' => $mailTemplateType]);
        if ($mailTemplateEntity === null) {
            $projectRootPath = $this->parameterBag->get('kernel.project_dir');
            $value = Yaml::parseFile("$projectRootPath/translations/".self::MAILING_TRANSLATION_FILENAME);
            $templates = $value['template'];
            foreach ($templates as $template) {
                if ($template['type'] !== $mailTemplateType->value) {
                    continue;
                }
                $mailTemplateEntity = new MailTemplate();
                $mailTemplateEntity->setName($template['name']);
                $mailTemplateEntity->setType(MailTemplateType::from($template['type']));
                $mailTemplateEntity->setSubject($template['subject']);
                $mailTemplateEntity->setMessage($template['message']);
                $this->entityManager->persist($mailTemplateEntity);
                $this->entityManager->flush();
            }
        }

        return $mailTemplateEntity;
    }

    /**
     * Send an email synchronously without queueing its rendered body.
     * Used for one-shot messages that carry sensitive links (password reset,
     * welcome). On success, a Mailing row with '[redacted]' as the body is
     * persisted for audit; the rendered link never touches the DB.
     */
    public function sendImmediate(MailTemplateType $mailTemplateType, User $user, array $extraPlaceholders = [], string $attachment = ''): bool
    {
        $template = $this->resolveTemplate($mailTemplateType);
        if ($template === null) {
            return false;
        }

        [$subject, $message, $resolvedAttachment] = $this->replacePlaceholders(
            $template->getSubject(),
            $template->getMessage(),
            $user,
            $extraPlaceholders,
            $attachment
        );

        $result = $this->sendMail(
            $user->getEmail(),
            "{$user->getName()} {$user->getSurname()}",
            $subject,
            $message,
            $resolvedAttachment !== '' ? $resolvedAttachment : null
        );

        if ($result === false) {
            $this->logger->log(LogLevel::ERROR, 'Immediate mail send failed', [
                'templateType' => $mailTemplateType->value,
                'userId' => $user->getId(),
            ]);

            return false;
        }

        $mailing = $this->buildMailing($user, $template, $subject, '[redacted]', $resolvedAttachment);
        $mailing->setStatus(\App\Enum\MailingStatus::SENT);
        $mailing->setSentDate(new \DateTime());
        $this->entityManager->persist($mailing);
        $this->entityManager->flush();

        return true;
    }

    public function addCustom(User $user, ?MailTemplate $template, array $extraPlaceholders = [], string $attachment = ''): void
    {
        [$subject, $message, $attachment] = $this->replacePlaceholders($template->getSubject(), $template->getMessage(), $user, $extraPlaceholders, $attachment);
        $mailing = $this->buildMailing($user, $template, $subject, $message, $attachment);
        $this->entityManager->persist($mailing);
        $this->entityManager->flush();
    }

    private function buildMailing(User $user, ?MailTemplate $template, string $subject, string $message, ?string $attachment): Mailing
    {
        $mailing = new Mailing();
        $mailing->setEmail($user->getEmail());
        if (in_array(Role::ADMINISTRATOR->string(), $user->getRoles(), true)) {
            $mailing->setUser($user);
        }
        $mailing->setName($user->getName());
        $mailing->setSurname($user->getSurname());
        $mailing->setSubject($subject);
        $mailing->setMessage($message);
        $mailing->setMailTemplate($template);
        $mailing->setAttachment($attachment);

        return $mailing;
    }

    private function replacePlaceholders(string $subject, string $message, User $user, array $extraPlaceholders = [], ?string $attachment = null): array
    {
        $find = ['[name]', '[surname]'];
        $replace = [$this->sanitizerService->sanitizeValue($user->getName()), $this->sanitizerService->sanitizeValue($user->getSurname())];
        $urlName = 'app_login_password-reset-hash';
        $isUserAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        if ($isUserAdmin) {
            $urlName = 'admin_login_password-reset-hash';
        }

        $subject = str_ireplace($find, $replace, $subject);
        $message = str_ireplace($find, $replace, $message);
        $plaintextResetToken = $user->getPlaintextPasswordResetHash();
        if (stristr($message, '[link]') !== false && $plaintextResetToken !== null && $plaintextResetToken !== '') {
            $link = $this->urlGenerator->generate($urlName, ['hash' => $plaintextResetToken], $this->urlGenerator::ABSOLUTE_URL);
            $message = str_ireplace('[link]', $link, $message);
            $message = str_ireplace('[linkValidity]', $user->getPasswordResetHashExpiration()->format('d-M-Y @ H:i'), $message);
        }
        foreach ($extraPlaceholders as $holder => $value) {
            $holder = ['['.$holder.']', '{'.$holder.'}'];
            $value = [$value, $value];
            $message = str_ireplace($holder, $value, $message);
            $subject = str_ireplace($holder, $value, $subject);
        }

        return [$subject, $message, $attachment];
    }

    public function addCustomWithNameAndEmail(
        string $subject,
        string $message,
        string $emailReceiver,
        string $nameReceiver,
        ?User $user,
        ?MailTemplate $template = null,
        array $extraPlaceholders = []
    ): void {
        $mailing = new Mailing();
        $mailing->setEmail($emailReceiver);

        if (strlen($nameReceiver) !== 0) {
            $names = explode(' ', $nameReceiver);
            $mailing->setName($names[0]);
            if (sizeof($names) > 1) {
                $mailing->setSurname($names[1]);
            }
        }
        if ($user !== null) {
            $mailing->setUser($user);
            [$subject, $message] = $this->replacePlaceholders($subject, $message, $user, $extraPlaceholders);
        }

        $mailing->setSubject($subject);
        $mailing->setMessage($message);
        if (isset($template)) {
            $mailing->setMailTemplate($template);
        }
        $this->entityManager->persist($mailing);
        $this->entityManager->flush();
    }

    public function processMailing(): void
    {
        $mailingRepo = $this->entityManager->getRepository(Mailing::class);
        /** @var Mailing[] $mails */
        $mails = $mailingRepo->findBy(['status' => \App\Enum\MailingStatus::NEW], null, self::MAX_MAILS_TO_PROCESS);
        foreach ($mails as $mail) {
            $emailAddress = filter_var($mail->getEmail(), FILTER_SANITIZE_EMAIL);
            if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                $mail->setStatus(\App\Enum\MailingStatus::PROCESSING);
            } else {
                $mail->setStatus(\App\Enum\MailingStatus::FAILED);
            }
        }
        $this->entityManager->flush();
        foreach ($mails as $mail) {
            $emailAddress = filter_var($mail->getEmail(), FILTER_SANITIZE_EMAIL);
            if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                $result = $this->sendMail(
                    $emailAddress,
                    "{$mail->getName()} {$mail->getSurname()}",
                    $mail->getSubject(),
                    $mail->getMessage(),
                    $mail->getAttachment(),
                );
                if ($result) {
                    $mail->setStatus(\App\Enum\MailingStatus::SENT);
                    $mail->setSentDate(new \DateTime());
                } else {
                    $mail->setStatus(\App\Enum\MailingStatus::NEW);
                }

                $this->entityManager->flush();
                // sleep for 1sec / rate limit seconds to make sure we don't violate sending rate limits
                usleep(intval(1000000 / self::SENDING_RATE_LIMITS_PER_SECOND));
            }
        }
    }

    /**
     * This is protected to make sure the function can be mocked in codeception DO NOT CHANGE PROTECTED.
     */
    protected function sendMail(
        string $to,
        string $toName,
        string $subject,
        string $message,
        ?string $attachmentFile = null,
        ?string $from = null,
        string $fromName = 'SAMMY Mailing System'
    ): bool {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->parameterBag->get('phpmailer.smtp.host');
            $mail->Port = (int) $this->parameterBag->get('phpmailer.smtp.port');
            $mail->SMTPAuth = $this->parameterBag->get('phpmailer.smtp.use.auth');;
            $mail->SMTPSecure = $this->parameterBag->get('phpmailer.smtp.default.encryption');
            $mail->Username = $this->parameterBag->get('phpmailer.smtp.username');
            $mail->Password = $this->parameterBag->get('phpmailer.smtp.password');
            $mail->SMTPAutoTLS = $this->parameterBag->get('phpmailer.smtp.auto.tls');

            if ($from === null) {
                $from = $this->parameterBag->get('phpmailer.smtp.default.sender');
            }

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to, $toName);
            $mail->isHTML();
            $mail->AltBody = strip_tags($message);
            $mail->Body = $message;
            $mail->Subject = $subject;

            if ($attachmentFile !== null) {
                $roodDir = $this->parameterBag->get('kernel.project_dir');
                $attachmentPath = "$roodDir/$attachmentFile";
                if ($this->filesystem->exists($attachmentPath) && is_file($attachmentPath)) {
                    $mail->addAttachment($attachmentPath);
                }
            }

            $result = $mail->send();
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::ERROR, 'Error sending mails', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $result = false;
        }

        return $result;
    }
}
