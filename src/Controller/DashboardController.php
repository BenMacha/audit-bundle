<?php

namespace BenMacha\AuditBundle\Controller;

use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use BenMacha\AuditBundle\Service\AuditManager;
use BenMacha\AuditBundle\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit', name: 'audit_')]
class DashboardController extends AbstractController
{
    private AuditManager $auditManager;
    private ConfigurationService $configurationService;
    private AuditLogRepository $auditLogRepository;
    private EntityConfigRepository $entityConfigRepository;

    public function __construct(
        AuditManager $auditManager,
        ConfigurationService $configurationService,
        AuditLogRepository $auditLogRepository,
        EntityConfigRepository $entityConfigRepository
    ) {
        $this->auditManager = $auditManager;
        $this->configurationService = $configurationService;
        $this->auditLogRepository = $auditLogRepository;
        $this->entityConfigRepository = $entityConfigRepository;
    }

    #[Route('/', name: 'dashboard')]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function dashboard(Request $request): Response
    {
        // Get date range from request (default to last 30 days)
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');

        if ($request->query->get('start_date')) {
            $startDate = new \DateTime($request->query->get('start_date'));
        }

        if ($request->query->get('end_date')) {
            $endDate = new \DateTime($request->query->get('end_date'));
        }

        // Get overall statistics
        $overallStats = $this->auditLogRepository->getStatistics();

        // Get statistics by operation
        $operationStats = $this->auditLogRepository->getStatisticsByOperation();

        // Get statistics by entity
        $entityStats = $this->auditLogRepository->getStatisticsByEntity(10); // Top 10

        // Get statistics by user
        $userStats = $this->auditLogRepository->getStatisticsByUser(10); // Top 10

        // Get daily statistics for the chart
        $dailyStats = $this->auditLogRepository->getDailyStatistics($startDate, $endDate);

        // Get recent activity
        $recentActivity = $this->auditLogRepository->getRecentActivity(20);

        // Get entity configurations
        $entityConfigs = $this->entityConfigRepository->findEnabledConfigurations();

        // Get audit configuration status
        $auditConfig = $this->configurationService->getGlobalAuditConfig();
        $isAuditEnabled = $this->configurationService->isAuditEnabled();

        // Calculate some derived metrics
        $metrics = $this->calculateMetrics($overallStats, $dailyStats, $startDate, $endDate);

        return $this->render('@Audit/dashboard/index.html.twig', [
            'overall_stats' => $overallStats,
            'operation_stats' => $operationStats,
            'entity_stats' => $entityStats,
            'user_stats' => $userStats,
            'daily_stats' => $dailyStats,
            'recent_activity' => $recentActivity,
            'entity_configs' => $entityConfigs,
            'audit_config' => $auditConfig,
            'is_audit_enabled' => $isAuditEnabled,
            'metrics' => $metrics,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'date_range_days' => $startDate->diff($endDate)->days,
        ]);
    }

    #[Route('/status', name: 'status')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function status(): Response
    {
        // Get system status information
        $status = [
            'audit_enabled' => $this->configurationService->isAuditEnabled(),
            'async_processing' => $this->configurationService->isAsyncProcessingEnabled(),
            'retention_days' => $this->configurationService->getRetentionDays(),
            'api_enabled' => $this->configurationService->isApiEnabled(),
            'rollback_enabled' => $this->configurationService->isRollbackEnabled(),
        ];

        // Get entity configurations status
        $entityConfigs = $this->entityConfigRepository->findAll();
        $entityStatus = [];

        foreach ($entityConfigs as $config) {
            $entityStatus[] = [
                'entity_class' => $config->getEntityClass(),
                'enabled' => $config->isEnabled(),
                'table_name' => $config->getEffectiveTableName(),
                'ignored_columns' => $config->getIgnoredColumns(),
                'audit_count' => $this->auditLogRepository->countByFilters([
                    'entity_class' => $config->getEntityClass(),
                ]),
            ];
        }

        // Get database status
        $databaseStatus = $this->checkDatabaseStatus();

        // Get performance metrics
        $performanceMetrics = $this->getPerformanceMetrics();

        return $this->render('@Audit/dashboard/status.html.twig', [
            'status' => $status,
            'entity_status' => $entityStatus,
            'database_status' => $databaseStatus,
            'performance_metrics' => $performanceMetrics,
        ]);
    }

    #[Route('/health', name: 'health')]
    public function health(): Response
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => new \DateTime(),
            'checks' => [],
        ];

        // Check if audit is enabled
        $health['checks']['audit_enabled'] = [
            'status' => $this->configurationService->isAuditEnabled() ? 'pass' : 'warn',
            'message' => $this->configurationService->isAuditEnabled() ? 'Audit is enabled' : 'Audit is disabled',
        ];

        // Check database connectivity
        try {
            $this->auditLogRepository->createQueryBuilder('al')
                ->select('COUNT(al.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $health['checks']['database'] = [
                'status' => 'pass',
                'message' => 'Database connection is working',
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        // Check recent audit activity
        $recentLogs = $this->auditLogRepository->getRecentActivity(1);
        $lastActivity = !empty($recentLogs) ? $recentLogs[0]->getCreatedAt() : null;

        if ($lastActivity && $lastActivity > new \DateTime('-1 hour')) {
            $health['checks']['recent_activity'] = [
                'status' => 'pass',
                'message' => 'Recent audit activity detected',
            ];
        } else {
            $health['checks']['recent_activity'] = [
                'status' => 'warn',
                'message' => 'No recent audit activity',
            ];
        }

        // Set overall status based on individual checks
        $hasFailures = array_filter($health['checks'], fn ($check) => 'fail' === $check['status']);
        if (!empty($hasFailures)) {
            $health['status'] = 'unhealthy';
        }

        return $this->json($health);
    }

    #[Route('/export', name: 'export')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $startDate = $request->query->get('start_date') ? new \DateTime($request->query->get('start_date')) : null;
        $endDate = $request->query->get('end_date') ? new \DateTime($request->query->get('end_date')) : null;
        $entityClass = $request->query->get('entity_class');
        $operation = $request->query->get('operation');

        // Build filters
        $filters = [];
        if ($startDate) {
            $filters['start_date'] = $startDate;
        }
        if ($endDate) {
            $filters['end_date'] = $endDate;
        }
        if ($entityClass) {
            $filters['entity_class'] = $entityClass;
        }
        if ($operation) {
            $filters['operation'] = $operation;
        }

        // Get audit logs
        $auditLogs = $this->auditLogRepository->findWithFilters($filters, null, null);

        // Generate export
        switch ($format) {
            case 'json':
                return $this->exportJson($auditLogs);
            case 'xml':
                return $this->exportXml($auditLogs);
            default:
                return $this->exportCsv($auditLogs);
        }
    }

    /**
     * Calculate derived metrics.
     */
    private function calculateMetrics(array $overallStats, array $dailyStats, \DateTime $startDate, \DateTime $endDate): array
    {
        $totalDays = max(1, $startDate->diff($endDate)->days);
        $totalLogs = $overallStats['total'] ?? 0;

        // Calculate average logs per day
        $avgLogsPerDay = $totalLogs > 0 ? round($totalLogs / $totalDays, 2) : 0;

        // Calculate trend (comparing first half vs second half of period)
        $midDate = (clone $startDate)->modify("+{$totalDays} days");
        $firstHalfLogs = array_sum(array_filter($dailyStats, fn ($stat) => $stat['date'] < $midDate->format('Y-m-d')));
        $secondHalfLogs = array_sum(array_filter($dailyStats, fn ($stat) => $stat['date'] >= $midDate->format('Y-m-d')));

        $trend = 'stable';
        if ($firstHalfLogs > 0) {
            $changePercent = (($secondHalfLogs - $firstHalfLogs) / $firstHalfLogs) * 100;
            if ($changePercent > 10) {
                $trend = 'increasing';
            } elseif ($changePercent < -10) {
                $trend = 'decreasing';
            }
        }

        return [
            'avg_logs_per_day' => $avgLogsPerDay,
            'trend' => $trend,
            'peak_day' => $this->findPeakDay($dailyStats),
            'total_days' => $totalDays,
        ];
    }

    /**
     * Find the day with most audit activity.
     */
    private function findPeakDay(array $dailyStats): ?array
    {
        if (empty($dailyStats)) {
            return null;
        }

        $peakDay = array_reduce($dailyStats, function ($carry, $item) {
            return (null === $carry || $item['count'] > $carry['count']) ? $item : $carry;
        });

        return $peakDay;
    }

    /**
     * Check database status.
     */
    private function checkDatabaseStatus(): array
    {
        try {
            // Check audit tables exist
            $connection = $this->auditLogRepository->getEntityManager()->getConnection();

            $tables = [
                'audit_config',
                'entity_config',
                'audit_log',
                'audit_change',
            ];

            $existingTables = [];
            foreach ($tables as $table) {
                $result = $connection->executeQuery(
                    'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = ?',
                    [$table]
                )->fetchAssociative();

                $existingTables[$table] = (int) $result['count'] > 0;
            }

            return [
                'connected' => true,
                'tables' => $existingTables,
                'all_tables_exist' => !in_array(false, $existingTables, true),
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'tables' => [],
                'all_tables_exist' => false,
            ];
        }
    }

    /**
     * Get performance metrics.
     */
    private function getPerformanceMetrics(): array
    {
        try {
            $connection = $this->auditLogRepository->getEntityManager()->getConnection();

            // Get table sizes
            $tableSizes = [];
            $tables = ['audit_log', 'audit_change', 'audit_config', 'entity_config'];

            foreach ($tables as $table) {
                try {
                    $result = $connection->executeQuery(
                        "SELECT COUNT(*) as row_count FROM {$table}"
                    )->fetchAssociative();

                    $tableSizes[$table] = (int) $result['row_count'];
                } catch (\Exception $e) {
                    $tableSizes[$table] = 0;
                }
            }

            return [
                'table_sizes' => $tableSizes,
                'total_audit_records' => $tableSizes['audit_log'] ?? 0,
                'total_change_records' => $tableSizes['audit_change'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'table_sizes' => [],
                'total_audit_records' => 0,
                'total_change_records' => 0,
            ];
        }
    }

    /**
     * Export audit logs as CSV.
     */
    private function exportCsv(array $auditLogs): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://temp', 'w');

        // Write CSV header
        fputcsv($output, [
            'ID', 'Entity Class', 'Entity ID', 'Operation', 'User ID', 'Username',
            'IP Address', 'User Agent', 'Created At', 'Old Values', 'New Values', 'Metadata',
        ]);

        // Write data rows
        foreach ($auditLogs as $log) {
            fputcsv($output, [
                $log->getId(),
                $log->getEntityClass(),
                $log->getEntityId(),
                $log->getOperation(),
                $log->getUserId(),
                $log->getUsername(),
                $log->getIpAddress(),
                $log->getUserAgent(),
                $log->getCreatedAt()->format('Y-m-d H:i:s'),
                $log->getOldValues(),
                $log->getNewValues(),
                $log->getMetadata(),
            ]);
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    /**
     * Export audit logs as JSON.
     */
    private function exportJson(array $auditLogs): Response
    {
        $data = [];
        foreach ($auditLogs as $log) {
            $data[] = [
                'id' => $log->getId(),
                'entity_class' => $log->getEntityClass(),
                'entity_id' => $log->getEntityId(),
                'operation' => $log->getOperation(),
                'user_id' => $log->getUserId(),
                'username' => $log->getUsername(),
                'ip_address' => $log->getIpAddress(),
                'user_agent' => $log->getUserAgent(),
                'created_at' => $log->getCreatedAt()->format('c'),
                'old_values' => json_decode($log->getOldValues(), true),
                'new_values' => json_decode($log->getNewValues(), true),
                'metadata' => json_decode($log->getMetadata(), true),
            ];
        }

        $response = new Response(json_encode($data, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d') . '.json"');

        return $response;
    }

    /**
     * Export audit logs as XML.
     */
    private function exportXml(array $auditLogs): Response
    {
        $xml = new \SimpleXMLElement('<audit_logs/>');

        foreach ($auditLogs as $log) {
            $logElement = $xml->addChild('audit_log');
            $logElement->addChild('id', $log->getId());
            $logElement->addChild('entity_class', htmlspecialchars($log->getEntityClass()));
            $logElement->addChild('entity_id', $log->getEntityId());
            $logElement->addChild('operation', $log->getOperation());
            $logElement->addChild('user_id', htmlspecialchars($log->getUserId() ?? ''));
            $logElement->addChild('username', htmlspecialchars($log->getUsername() ?? ''));
            $logElement->addChild('ip_address', htmlspecialchars($log->getIpAddress() ?? ''));
            $logElement->addChild('user_agent', htmlspecialchars($log->getUserAgent() ?? ''));
            $logElement->addChild('created_at', $log->getCreatedAt()->format('c'));
            $logElement->addChild('old_values', htmlspecialchars($log->getOldValues() ?? ''));
            $logElement->addChild('new_values', htmlspecialchars($log->getNewValues() ?? ''));
            $logElement->addChild('metadata', htmlspecialchars($log->getMetadata() ?? ''));
        }

        $response = new Response($xml->asXML());
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d') . '.xml"');

        return $response;
    }
}
