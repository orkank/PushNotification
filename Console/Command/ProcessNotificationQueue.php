<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use IDangerous\PushNotification\Model\ResourceModel\NotificationLog\CollectionFactory as NotificationLogCollectionFactory;
use IDangerous\PushNotification\Api\PushNotificationServiceInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;

class ProcessNotificationQueue extends Command
{
    private const COMMAND_NAME = 'idangerous:pushnotification:process-queue';

    private NotificationLogCollectionFactory $notificationLogCollectionFactory;
    private PushNotificationServiceInterface $pushNotificationService;
    private DateTime $dateTime;
    private LoggerInterface $logger;
    private Json $json;

    public function __construct(
        NotificationLogCollectionFactory $notificationLogCollectionFactory,
        PushNotificationServiceInterface $pushNotificationService,
        DateTime $dateTime,
        LoggerInterface $logger,
        Json $json
    ) {
        $this->notificationLogCollectionFactory = $notificationLogCollectionFactory;
        $this->pushNotificationService = $pushNotificationService;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->json = $json;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Process pending push notification queue')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Number of notifications to process (default: 10)',
                10
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_OPTIONAL,
                'Process notifications with specific status (pending, processing, failed)',
                'pending'
            )
            ->addOption(
                'force-retry',
                'f',
                InputOption::VALUE_NONE,
                'Force retry failed notifications'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = (int)$input->getOption('limit');
        $status = $input->getOption('status');
        $forceRetry = $input->getOption('force-retry');

        $output->writeln('<info>Starting push notification queue processing...</info>');
        $output->writeln("Processing notifications with status: <comment>{$status}</comment>");
        $output->writeln("Limit: <comment>{$limit}</comment>");

        // If force retry is enabled, reset failed notifications to pending
        if ($forceRetry) {
            $this->resetFailedNotifications($output);
        }

        $pendingNotifications = $this->notificationLogCollectionFactory->create()
            ->addStatusFilter($status)
            ->setPageSize($limit)
            ->setOrder('created_at', 'ASC')
            ->load();

        if ($pendingNotifications->getSize() == 0) {
            $output->writeln('<comment>No pending notifications found.</comment>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln("Found <info>{$pendingNotifications->getSize()}</info> notifications to process.");
        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($pendingNotifications as $notificationLog) {
            try {
                $output->writeln("Processing notification ID: <comment>{$notificationLog->getId()}</comment>");

                // Debug: Log emoji debugging info
                $title = $notificationLog->getTitle();
                $message = $notificationLog->getMessage();

                $this->logger->debug("PushNotification Console: Processing notification", [
                    'id' => $notificationLog->getId(),
                    'title' => $title,
                    'message' => $message,
                    'title_hex' => bin2hex($title),
                    'message_hex' => bin2hex($message),
                    'title_encoding' => mb_detect_encoding($title),
                    'message_encoding' => mb_detect_encoding($message)
                ]);

                $output->writeln("  Title: {$title}");
                $output->writeln("  Message: {$message}");

                // Set status to processing
                $notificationLog->setStatus('processing');
                $notificationLog->save();

                $filtersArray = $notificationLog->getFilters() ?: [];

                $output->writeln("  Filters: " . json_encode($filtersArray));

                $result = $this->pushNotificationService->sendToMultipleUsers(
                    $notificationLog->getTitle(),
                    $notificationLog->getMessage(),
                    $filtersArray,
                    $notificationLog->getImageUrl(),
                    $notificationLog->getActionUrl(),
                    $notificationLog->getNotificationType()
                );

                // Update notification log with results
                $notificationLog->setTotalSent($result['total_sent'] ?? 0);
                $notificationLog->setTotalFailed($result['total_failed'] ?? 0);
                $notificationLog->setStatus($result['success'] ? 'completed' : 'failed');
                $notificationLog->setErrorMessage(isset($result['message']) ? (string)$result['message'] : null);
                $notificationLog->setProcessedAt($this->dateTime->gmtDate());
                $notificationLog->save();

                if ($result['success']) {
                    $output->writeln("  <info>✓ Success</info> - Sent: {$result['total_sent']}, Failed: {$result['total_failed']}");
                    $successful++;
                } else {
                    $output->writeln("  <error>✗ Failed</error> - Error: {$result['message']}");
                    $failed++;
                }

                $processed++;

            } catch (\Exception $e) {
                $this->logger->error(
                    'PushNotification Console: Error processing notification log ID ' . $notificationLog->getId() . ': ' . $e->getMessage()
                );

                $notificationLog->setStatus('failed');
                $notificationLog->setErrorMessage($e->getMessage());
                $notificationLog->setProcessedAt($this->dateTime->gmtDate());
                $notificationLog->save();

                $output->writeln("  <error>✗ Exception</error> - {$e->getMessage()}");
                $failed++;
                $processed++;
            }
        }

        $output->writeln('');
        $output->writeln('<info>Processing completed!</info>');
        $output->writeln("Total processed: <comment>{$processed}</comment>");
        $output->writeln("Successful: <info>{$successful}</info>");
        $output->writeln("Failed: <error>{$failed}</error>");

        return Cli::RETURN_SUCCESS;
    }

    private function resetFailedNotifications(OutputInterface $output): void
    {
        $failedNotifications = $this->notificationLogCollectionFactory->create()
            ->addStatusFilter('failed')
            ->load();

        if ($failedNotifications->getSize() > 0) {
            $output->writeln("<info>Resetting {$failedNotifications->getSize()} failed notifications to pending...</info>");

            foreach ($failedNotifications as $notification) {
                $notification->setStatus('pending');
                $notification->setErrorMessage(null);
                $notification->setProcessedAt(null);
                $notification->save();
            }
        }
    }
}
