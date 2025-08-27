<?php

namespace BenMacha\AuditBundle\Controller\Api;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Service\AuditManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/audit-logs', name: 'api_audit_logs_')]
class AuditLogApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogRepository $auditLogRepository,
        private AuditManager $auditManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $filters = [
            'entity_class' => $request->query->get('entity_class'),
            'entity_id' => $request->query->get('entity_id'),
            'operation' => $request->query->get('operation'),
            'user_id' => $request->query->get('user_id'),
            'username' => $request->query->get('username'),
            'ip_address' => $request->query->get('ip_address'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'search' => $request->query->get('search'),
        ];

        $orderBy = $request->query->get('order_by', 'createdAt');
        $orderDir = strtoupper($request->query->get('order_dir', 'DESC'));
        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }

        $result = $this->auditLogRepository->findWithFilters(
            $filters,
            $orderBy,
            $orderDir,
            $limit,
            $offset
        );

        $total = $this->auditLogRepository->countByFilters($filters);
        $totalPages = ceil($total / $limit);

        $data = [];
        foreach ($result as $auditLog) {
            $data[] = $this->serializeAuditLog($auditLog);
        }

        return new JsonResponse([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
            'filters' => array_filter($filters),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function show(int $id): JsonResponse
    {
        $auditLog = $this->auditLogRepository->find($id);

        if (!$auditLog) {
            return new JsonResponse(['error' => 'Audit log not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'data' => $this->serializeAuditLog($auditLog, true),
        ]);
    }

    #[Route('/entity/{entityClass}/{entityId}', name: 'by_entity', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function byEntity(string $entityClass, string $entityId, Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $decodedEntityClass = base64_decode($entityClass);
        if (!$decodedEntityClass || !class_exists($decodedEntityClass)) {
            return new JsonResponse(['error' => 'Invalid entity class'], Response::HTTP_BAD_REQUEST);
        }

        $auditLogs = $this->auditLogRepository->findByEntity(
            $decodedEntityClass,
            $entityId,
            $limit,
            $offset
        );

        $total = $this->auditLogRepository->countByEntity($decodedEntityClass, $entityId);
        $totalPages = ceil($total / $limit);

        $data = [];
        foreach ($auditLogs as $auditLog) {
            $data[] = $this->serializeAuditLog($auditLog);
        }

        return new JsonResponse([
            'data' => $data,
            'entity_class' => $decodedEntityClass,
            'entity_id' => $entityId,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
        ]);
    }

    #[Route('/user/{userId}', name: 'by_user', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function byUser(int $userId, Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $auditLogs = $this->auditLogRepository->findByUser(
            $userId,
            $limit,
            $offset
        );

        $total = $this->auditLogRepository->countByUser($userId);
        $totalPages = ceil($total / $limit);

        $data = [];
        foreach ($auditLogs as $auditLog) {
            $data[] = $this->serializeAuditLog($auditLog);
        }

        return new JsonResponse([
            'data' => $data,
            'user_id' => $userId,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
        ]);
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function statistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');

        $statistics = $this->auditLogRepository->getStatistics($dateFrom, $dateTo, $entityClass);
        $operationStats = $this->auditLogRepository->getStatisticsByOperation($dateFrom, $dateTo, $entityClass);
        $entityStats = $this->auditLogRepository->getStatisticsByEntity($dateFrom, $dateTo);
        $userStats = $this->auditLogRepository->getStatisticsByUser($dateFrom, $dateTo, $entityClass);
        $dailyStats = $this->auditLogRepository->getDailyStatistics($dateFrom, $dateTo, $entityClass);

        return new JsonResponse([
            'overall' => $statistics,
            'by_operation' => $operationStats,
            'by_entity' => $entityStats,
            'by_user' => $userStats,
            'daily' => $dailyStats,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'entity_class' => $entityClass,
            ],
        ]);
    }

    #[Route('/recent', name: 'recent', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function recent(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $entityClass = $request->query->get('entity_class');
        $userId = $request->query->get('user_id');

        $recentActivity = $this->auditLogRepository->getRecentActivity($limit, $entityClass, $userId);

        $data = [];
        foreach ($recentActivity as $auditLog) {
            $data[] = $this->serializeAuditLog($auditLog);
        }

        return new JsonResponse([
            'data' => $data,
            'limit' => $limit,
            'filters' => [
                'entity_class' => $entityClass,
                'user_id' => $userId,
            ],
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_EXPORT')]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'json');
        $limit = min(10000, max(1, (int) $request->query->get('limit', 1000)));

        $filters = [
            'entity_class' => $request->query->get('entity_class'),
            'entity_id' => $request->query->get('entity_id'),
            'operation' => $request->query->get('operation'),
            'user_id' => $request->query->get('user_id'),
            'username' => $request->query->get('username'),
            'ip_address' => $request->query->get('ip_address'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
        ];

        $auditLogs = $this->auditLogRepository->findWithFilters(
            $filters,
            'createdAt',
            'DESC',
            $limit
        );

        $data = [];
        foreach ($auditLogs as $auditLog) {
            $data[] = $this->serializeAuditLog($auditLog, true);
        }

        $filename = 'audit_logs_' . date('Y-m-d_H-i-s');

        switch ($format) {
            case 'csv':
                return $this->exportCsv($data, $filename);
            case 'xml':
                return $this->exportXml($data, $filename);
            default:
                return $this->exportJson($data, $filename);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_AUDIT_DELETE')]
    public function delete(int $id): JsonResponse
    {
        $auditLog = $this->auditLogRepository->find($id);

        if (!$auditLog) {
            return new JsonResponse(['error' => 'Audit log not found'], Response::HTTP_NOT_FOUND);
        }

        $this->auditLogRepository->remove($auditLog, true);

        return new JsonResponse(['message' => 'Audit log deleted successfully']);
    }

    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function cleanup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $days = $data['days'] ?? 30;
        $entityClass = $data['entity_class'] ?? null;
        $dryRun = $data['dry_run'] ?? false;

        if ($days < 1) {
            return new JsonResponse(['error' => 'Days must be at least 1'], Response::HTTP_BAD_REQUEST);
        }

        $cutoffDate = new \DateTime("-{$days} days");

        if ($dryRun) {
            $count = $this->auditLogRepository->countOlderThan($cutoffDate, $entityClass);

            return new JsonResponse([
                'message' => 'Dry run completed',
                'would_delete' => $count,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                'entity_class' => $entityClass,
            ]);
        }

        $deletedCount = $this->auditLogRepository->deleteOlderThan($cutoffDate, $entityClass);

        return new JsonResponse([
            'message' => 'Cleanup completed',
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'entity_class' => $entityClass,
        ]);
    }

    private function serializeAuditLog(AuditLog $auditLog, bool $includeChanges = false): array
    {
        $data = [
            'id' => $auditLog->getId(),
            'entity_class' => $auditLog->getEntityClass(),
            'entity_id' => $auditLog->getEntityId(),
            'operation' => $auditLog->getOperation(),
            'user_id' => $auditLog->getUserId(),
            'username' => $auditLog->getUsername(),
            'ip_address' => $auditLog->getIpAddress(),
            'user_agent' => $auditLog->getUserAgent(),
            'created_at' => $auditLog->getCreatedAt()->format('c'),
            'metadata' => $auditLog->getMetadata(),
        ];

        if ($includeChanges) {
            $data['old_values'] = $auditLog->getOldValues();
            $data['new_values'] = $auditLog->getNewValues();

            $changes = [];
            foreach ($auditLog->getChanges() as $change) {
                $changes[] = [
                    'id' => $change->getId(),
                    'field_name' => $change->getFieldName(),
                    'field_type' => $change->getFieldType(),
                    'old_value' => $change->getOldValue(),
                    'new_value' => $change->getNewValue(),
                    'old_value_formatted' => $change->getOldValueFormatted(),
                    'new_value_formatted' => $change->getNewValueFormatted(),
                    'change_type' => $change->getChangeType(),
                ];
            }
            $data['changes'] = $changes;
        }

        return $data;
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

        if (!empty($data)) {
            // Write header
            fputcsv($output, array_keys($data[0]));

            // Write data
            foreach ($data as $row) {
                // Flatten complex fields
                $flatRow = [];
                foreach ($row as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        $flatRow[$key] = json_encode($value);
                    } else {
                        $flatRow[$key] = $value;
                    }
                }
                fputcsv($output, $flatRow);
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
        $xml = new \SimpleXMLElement('<audit_logs/>');

        foreach ($data as $auditLogData) {
            $auditLogXml = $xml->addChild('audit_log');
            foreach ($auditLogData as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $auditLogXml->addChild($key, htmlspecialchars(json_encode($value)));
                } else {
                    $auditLogXml->addChild($key, htmlspecialchars((string) $value));
                }
            }
        }

        $response = new Response($xml->asXML());
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.xml\"");

        return $response;
    }
}
