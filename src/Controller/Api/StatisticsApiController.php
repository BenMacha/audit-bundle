<?php

namespace BenMacha\AuditBundle\Controller\Api;

use BenMacha\AuditBundle\Repository\AuditChangeRepository;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use BenMacha\AuditBundle\Service\AuditManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/statistics', name: 'api_statistics_')]
class StatisticsApiController extends AbstractController
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private AuditChangeRepository $auditChangeRepository,
        private EntityConfigRepository $entityConfigRepository,
        private AuditManager $auditManager
    ) {
    }

    #[Route('/overview', name: 'overview', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getOverview(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');

        // Overall statistics
        $overallStats = $this->auditLogRepository->getStatistics($dateFrom, $dateTo, $entityClass);

        // Operation breakdown
        $operationStats = $this->auditLogRepository->getStatisticsByOperation($dateFrom, $dateTo, $entityClass);

        // Entity breakdown
        $entityStats = $this->auditLogRepository->getStatisticsByEntity($dateFrom, $dateTo);

        // User activity
        $userStats = $this->auditLogRepository->getStatisticsByUser($dateFrom, $dateTo, $entityClass);

        // Daily activity
        $dailyStats = $this->auditLogRepository->getDailyStatistics($dateFrom, $dateTo, $entityClass);

        // Configuration stats
        $configStats = $this->entityConfigRepository->getStatistics();

        // Recent activity
        $recentActivity = $this->auditLogRepository->getRecentActivity(10, $entityClass);

        $recentData = [];
        foreach ($recentActivity as $auditLog) {
            $recentData[] = [
                'id' => $auditLog->getId(),
                'entity_class' => $auditLog->getEntityClass(),
                'entity_id' => $auditLog->getEntityId(),
                'operation' => $auditLog->getOperation(),
                'username' => $auditLog->getUsername(),
                'created_at' => $auditLog->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse([
            'data' => [
                'overall' => $overallStats,
                'by_operation' => $operationStats,
                'by_entity' => $entityStats,
                'by_user' => $userStats,
                'daily_activity' => $dailyStats,
                'configuration' => $configStats,
                'recent_activity' => $recentData,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'entity_class' => $entityClass,
            ],
            'generated_at' => (new \DateTime())->format('c'),
        ]);
    }

    #[Route('/operations', name: 'operations', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getOperationStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');
        $groupBy = $request->query->get('group_by', 'operation'); // operation, entity, user, date

        $statistics = [];

        switch ($groupBy) {
            case 'operation':
                $statistics = $this->auditLogRepository->getStatisticsByOperation($dateFrom, $dateTo, $entityClass);
                break;
            case 'entity':
                $statistics = $this->auditLogRepository->getStatisticsByEntity($dateFrom, $dateTo);
                break;
            case 'user':
                $statistics = $this->auditLogRepository->getStatisticsByUser($dateFrom, $dateTo, $entityClass);
                break;
            case 'date':
                $statistics = $this->auditLogRepository->getDailyStatistics($dateFrom, $dateTo, $entityClass);
                break;
            default:
                return new JsonResponse(['error' => 'Invalid group_by parameter'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'data' => $statistics,
            'group_by' => $groupBy,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'entity_class' => $entityClass,
            ],
        ]);
    }

    #[Route('/entities', name: 'entities', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getEntityStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $includeConfig = 'true' === $request->query->get('include_config', 'true');

        // Audit log statistics by entity
        $auditStats = $this->auditLogRepository->getStatisticsByEntity($dateFrom, $dateTo);

        // Entity configuration statistics
        $configStats = [];
        if ($includeConfig) {
            $configStats = $this->entityConfigRepository->getStatistics();
        }

        // Combine data
        $combinedStats = [];
        foreach ($auditStats as $stat) {
            $entityClass = $stat['entity_class'];
            $combinedStats[$entityClass] = [
                'entity_class' => $entityClass,
                'audit_logs' => [
                    'total' => $stat['total'],
                    'inserts' => $stat['inserts'] ?? 0,
                    'updates' => $stat['updates'] ?? 0,
                    'deletes' => $stat['deletes'] ?? 0,
                ],
                'configuration' => null,
            ];
        }

        if ($includeConfig) {
            foreach ($configStats as $stat) {
                $entityClass = $stat['entity_class'];
                if (!isset($combinedStats[$entityClass])) {
                    $combinedStats[$entityClass] = [
                        'entity_class' => $entityClass,
                        'audit_logs' => [
                            'total' => 0,
                            'inserts' => 0,
                            'updates' => 0,
                            'deletes' => 0,
                        ],
                        'configuration' => null,
                    ];
                }
                $combinedStats[$entityClass]['configuration'] = [
                    'enabled' => $stat['enabled'],
                    'create_table' => $stat['create_table'],
                    'table_name' => $stat['table_name'],
                    'ignored_columns_count' => count($stat['ignored_columns'] ?? []),
                ];
            }
        }

        return new JsonResponse([
            'data' => array_values($combinedStats),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'include_config' => $includeConfig,
            ],
        ]);
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getUserStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $userStats = $this->auditLogRepository->getStatisticsByUser($dateFrom, $dateTo, $entityClass, $limit);

        // Get unique users for the period
        $uniqueUsers = $this->auditLogRepository->getUniqueUsers($dateFrom, $dateTo, $entityClass);

        return new JsonResponse([
            'data' => [
                'user_statistics' => $userStats,
                'summary' => [
                    'total_unique_users' => count($uniqueUsers),
                    'top_users_shown' => count($userStats),
                ],
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'entity_class' => $entityClass,
                'limit' => $limit,
            ],
        ]);
    }

    #[Route('/changes', name: 'changes', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getChangeStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');
        $fieldName = $request->query->get('field_name');
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        // Field change statistics
        $fieldStats = $this->auditChangeRepository->getFieldChangeStatistics(
            $dateFrom,
            $dateTo,
            $entityClass,
            $limit
        );

        // Most changed fields
        $mostChangedFields = $this->auditChangeRepository->getMostChangedFields(
            $dateFrom,
            $dateTo,
            $entityClass,
            $limit
        );

        // Changes by field type
        $changesByType = $this->auditChangeRepository->getChangesByFieldType(
            $dateFrom,
            $dateTo,
            $entityClass
        );

        // Recent changes
        $recentChanges = $this->auditChangeRepository->getRecentChanges(
            min(20, $limit),
            $entityClass,
            $fieldName
        );

        $recentData = [];
        foreach ($recentChanges as $change) {
            $recentData[] = [
                'id' => $change->getId(),
                'field_name' => $change->getFieldName(),
                'field_type' => $change->getFieldType(),
                'old_value_formatted' => $change->getOldValueFormatted(),
                'new_value_formatted' => $change->getNewValueFormatted(),
                'change_type' => $change->getChangeType(),
                'audit_log_id' => $change->getAuditLog()->getId(),
                'entity_class' => $change->getAuditLog()->getEntityClass(),
                'entity_id' => $change->getAuditLog()->getEntityId(),
                'created_at' => $change->getAuditLog()->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse([
            'data' => [
                'field_statistics' => $fieldStats,
                'most_changed_fields' => $mostChangedFields,
                'changes_by_type' => $changesByType,
                'recent_changes' => $recentData,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'entity_class' => $entityClass,
                'field_name' => $fieldName,
                'limit' => $limit,
            ],
        ]);
    }

    #[Route('/timeline', name: 'timeline', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getTimelineStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');
        $granularity = $request->query->get('granularity', 'day'); // hour, day, week, month

        if (!in_array($granularity, ['hour', 'day', 'week', 'month'])) {
            return new JsonResponse(['error' => 'Invalid granularity'], Response::HTTP_BAD_REQUEST);
        }

        // Get timeline data based on granularity
        switch ($granularity) {
            case 'hour':
                $timelineData = $this->auditLogRepository->getHourlyStatistics($dateFrom, $dateTo, $entityClass);
                break;
            case 'day':
                $timelineData = $this->auditLogRepository->getDailyStatistics($dateFrom, $dateTo, $entityClass);
                break;
            case 'week':
                $timelineData = $this->auditLogRepository->getWeeklyStatistics($dateFrom, $dateTo, $entityClass);
                break;
            case 'month':
                $timelineData = $this->auditLogRepository->getMonthlyStatistics($dateFrom, $dateTo, $entityClass);
                break;
        }

        return new JsonResponse([
            'data' => $timelineData,
            'granularity' => $granularity,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'entity_class' => $entityClass,
            ],
        ]);
    }

    #[Route('/performance', name: 'performance', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getPerformanceStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        // Get audit manager statistics
        $auditStats = $this->auditManager->getAuditStatistics($dateFrom, $dateTo);

        // Database performance metrics
        $dbStats = [
            'total_audit_logs' => $this->auditLogRepository->count([]),
            'total_audit_changes' => $this->auditChangeRepository->count([]),
            'total_entity_configs' => $this->entityConfigRepository->count([]),
            'enabled_entity_configs' => $this->entityConfigRepository->count(['enabled' => true]),
        ];

        // Storage usage estimation
        $storageStats = $this->calculateStorageStatistics();

        // System health indicators
        $healthStats = [
            'audit_enabled' => $this->auditManager->isAuditingEnabled(),
            'async_processing' => $this->auditManager->isAsyncProcessingEnabled(),
            'retention_days' => $this->auditManager->getRetentionDays(),
            'last_cleanup' => $this->getLastCleanupDate(),
        ];

        return new JsonResponse([
            'data' => [
                'audit_performance' => $auditStats,
                'database_metrics' => $dbStats,
                'storage_usage' => $storageStats,
                'system_health' => $healthStats,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'generated_at' => (new \DateTime())->format('c'),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_EXPORT')]
    public function exportStatistics(Request $request): Response
    {
        $format = $request->query->get('format', 'json');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');

        // Gather all statistics
        $data = [
            'overview' => [
                'overall' => $this->auditLogRepository->getStatistics($dateFrom, $dateTo, $entityClass),
                'by_operation' => $this->auditLogRepository->getStatisticsByOperation($dateFrom, $dateTo, $entityClass),
                'by_entity' => $this->auditLogRepository->getStatisticsByEntity($dateFrom, $dateTo),
                'by_user' => $this->auditLogRepository->getStatisticsByUser($dateFrom, $dateTo, $entityClass),
                'daily_activity' => $this->auditLogRepository->getDailyStatistics($dateFrom, $dateTo, $entityClass),
            ],
            'configuration' => $this->entityConfigRepository->getStatistics(),
            'changes' => [
                'field_statistics' => $this->auditChangeRepository->getFieldChangeStatistics($dateFrom, $dateTo, $entityClass),
                'most_changed_fields' => $this->auditChangeRepository->getMostChangedFields($dateFrom, $dateTo, $entityClass),
                'changes_by_type' => $this->auditChangeRepository->getChangesByFieldType($dateFrom, $dateTo, $entityClass),
            ],
            'metadata' => [
                'exported_at' => (new \DateTime())->format('c'),
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'entity_class' => $entityClass,
                ],
                'format' => $format,
            ],
        ];

        $filename = 'audit_statistics_' . date('Y-m-d_H-i-s');

        switch ($format) {
            case 'csv':
                return $this->exportCsv($data, $filename);
            case 'xml':
                return $this->exportXml($data, $filename);
            default:
                return $this->exportJson($data, $filename);
        }
    }

    private function calculateStorageStatistics(): array
    {
        // This is a simplified estimation - in a real implementation,
        // you might query the database for actual table sizes
        return [
            'estimated_audit_logs_size_mb' => round($this->auditLogRepository->count([]) * 0.001, 2),
            'estimated_audit_changes_size_mb' => round($this->auditChangeRepository->count([]) * 0.0005, 2),
            'estimated_total_size_mb' => round(($this->auditLogRepository->count([]) * 0.001) + ($this->auditChangeRepository->count([]) * 0.0005), 2),
        ];
    }

    private function getLastCleanupDate(): ?string
    {
        // This would typically be stored in a configuration or log table
        // For now, return null as placeholder
        return null;
    }

    private function exportJson(array $data, string $filename): Response
    {
        $response = new Response(json_encode($data, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.json\"");

        return $response;
    }

    private function exportCsv(array $data, string $filename): Response
    {
        $output = fopen('php://temp', 'r+');

        // Flatten the nested array structure for CSV
        $flatData = $this->flattenArray($data);

        if (!empty($flatData)) {
            fputcsv($output, ['Category', 'Metric', 'Value']);

            foreach ($flatData as $key => $value) {
                $parts = explode('.', $key);
                $category = $parts[0] ?? 'unknown';
                $metric = implode('.', array_slice($parts, 1));
                fputcsv($output, [$category, $metric, is_array($value) ? json_encode($value) : $value]);
            }
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.csv\"");

        return $response;
    }

    private function exportXml(array $data, string $filename): Response
    {
        $xml = new \SimpleXMLElement('<audit_statistics/>');
        $this->arrayToXml($data, $xml);

        $response = new Response($xml->asXML());
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.xml\"");

        return $response;
    }

    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }
}
