<?php

namespace BenMacha\AuditBundle\Controller\Api;

use BenMacha\AuditBundle\Entity\AuditConfig;
use BenMacha\AuditBundle\Entity\EntityConfig;
use BenMacha\AuditBundle\Repository\AuditConfigRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use BenMacha\AuditBundle\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/configuration', name: 'api_configuration_')]
class ConfigurationApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditConfigRepository $auditConfigRepository,
        private EntityConfigRepository $entityConfigRepository,
        private ConfigurationService $configurationService,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/global', name: 'global_get', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function getGlobalConfig(): JsonResponse
    {
        $config = $this->configurationService->getGlobalAuditConfig();

        return new JsonResponse([
            'data' => $this->serializeAuditConfig($config),
        ]);
    }

    #[Route('/global', name: 'global_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function updateGlobalConfig(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        $config = $this->configurationService->getGlobalAuditConfig();

        // Update basic settings
        if (isset($data['enabled'])) {
            $config->setEnabled((bool) $data['enabled']);
        }

        if (isset($data['name'])) {
            $config->setName($data['name']);
        }

        // Update settings
        $settings = $config->getSettings() ?? [];

        if (isset($data['retention_days'])) {
            $settings['retention_days'] = max(1, (int) $data['retention_days']);
        }

        if (isset($data['async_processing'])) {
            $settings['async_processing'] = (bool) $data['async_processing'];
        }

        if (isset($data['api_settings'])) {
            $settings['api'] = array_merge($settings['api'] ?? [], $data['api_settings']);
        }

        if (isset($data['security_settings'])) {
            $settings['security'] = array_merge($settings['security'] ?? [], $data['security_settings']);
        }

        if (isset($data['ui_settings'])) {
            $settings['ui'] = array_merge($settings['ui'] ?? [], $data['ui_settings']);
        }

        $config->setSettings($settings);
        $config->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($config);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Global configuration updated successfully',
            'data' => $this->serializeAuditConfig($config),
        ]);
    }

    #[Route('/entities', name: 'entities_list', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function listEntityConfigs(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $enabled = $request->query->get('enabled');
        $search = $request->query->get('search');

        $configs = $this->entityConfigRepository->findWithFilters(
            null !== $enabled ? (bool) $enabled : null,
            $search,
            $limit,
            $offset
        );

        $total = $this->entityConfigRepository->countWithFilters(
            null !== $enabled ? (bool) $enabled : null,
            $search
        );

        $totalPages = ceil($total / $limit);

        $data = [];
        foreach ($configs as $config) {
            $data[] = $this->serializeEntityConfig($config);
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
            'filters' => [
                'enabled' => $enabled,
                'search' => $search,
            ],
        ]);
    }

    #[Route('/entities/{id}', name: 'entity_get', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function getEntityConfig(int $id): JsonResponse
    {
        $config = $this->entityConfigRepository->find($id);

        if (!$config) {
            return new JsonResponse(['error' => 'Entity configuration not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'data' => $this->serializeEntityConfig($config, true),
        ]);
    }

    #[Route('/entities', name: 'entity_create', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function createEntityConfig(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['entity_class'])) {
            return new JsonResponse(['error' => 'Entity class is required'], Response::HTTP_BAD_REQUEST);
        }

        $entityClass = $data['entity_class'];

        if (!class_exists($entityClass)) {
            return new JsonResponse(['error' => 'Entity class does not exist'], Response::HTTP_BAD_REQUEST);
        }

        // Check if configuration already exists
        $existingConfig = $this->entityConfigRepository->findByEntityClass($entityClass);
        if ($existingConfig) {
            return new JsonResponse(['error' => 'Configuration for this entity already exists'], Response::HTTP_CONFLICT);
        }

        $auditConfig = $this->configurationService->getGlobalAuditConfig();

        $config = new EntityConfig();
        $config->setEntityClass($entityClass);
        $config->setEnabled($data['enabled'] ?? true);
        $config->setCreateTable($data['create_table'] ?? true);
        $config->setTableName($data['table_name'] ?? null);
        $config->setIgnoredColumns($data['ignored_columns'] ?? []);
        $config->setSettings($data['settings'] ?? []);
        $config->setAuditConfig($auditConfig);
        $config->setCreatedAt(new \DateTime());
        $config->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($config);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Entity configuration created successfully',
            'data' => $this->serializeEntityConfig($config, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/entities/{id}', name: 'entity_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function updateEntityConfig(int $id, Request $request): JsonResponse
    {
        $config = $this->entityConfigRepository->find($id);

        if (!$config) {
            return new JsonResponse(['error' => 'Entity configuration not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        // Update fields
        if (isset($data['enabled'])) {
            $config->setEnabled((bool) $data['enabled']);
        }

        if (isset($data['create_table'])) {
            $config->setCreateTable((bool) $data['create_table']);
        }

        if (isset($data['table_name'])) {
            $config->setTableName($data['table_name']);
        }

        if (isset($data['ignored_columns'])) {
            $config->setIgnoredColumns($data['ignored_columns']);
        }

        if (isset($data['settings'])) {
            $settings = $config->getSettings() ?? [];
            $config->setSettings(array_merge($settings, $data['settings']));
        }

        $config->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($config);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Entity configuration updated successfully',
            'data' => $this->serializeEntityConfig($config, true),
        ]);
    }

    #[Route('/entities/{id}', name: 'entity_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function deleteEntityConfig(int $id): JsonResponse
    {
        $config = $this->entityConfigRepository->find($id);

        if (!$config) {
            return new JsonResponse(['error' => 'Entity configuration not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($config);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Entity configuration deleted successfully']);
    }

    #[Route('/entities/bulk', name: 'entities_bulk_update', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function bulkUpdateEntityConfigs(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['action']) || !isset($data['entity_ids'])) {
            return new JsonResponse(['error' => 'Action and entity_ids are required'], Response::HTTP_BAD_REQUEST);
        }

        $action = $data['action'];
        $entityIds = $data['entity_ids'];
        $value = $data['value'] ?? null;

        if (!in_array($action, ['enable', 'disable', 'create_table', 'delete'])) {
            return new JsonResponse(['error' => 'Invalid action'], Response::HTTP_BAD_REQUEST);
        }

        $configs = $this->entityConfigRepository->findBy(['id' => $entityIds]);

        if (count($configs) !== count($entityIds)) {
            return new JsonResponse(['error' => 'Some entity configurations not found'], Response::HTTP_NOT_FOUND);
        }

        $updated = 0;
        $deleted = 0;

        foreach ($configs as $config) {
            switch ($action) {
                case 'enable':
                    $config->setEnabled(true);
                    $config->setUpdatedAt(new \DateTime());
                    ++$updated;
                    break;
                case 'disable':
                    $config->setEnabled(false);
                    $config->setUpdatedAt(new \DateTime());
                    ++$updated;
                    break;
                case 'create_table':
                    $config->setCreateTable((bool) $value);
                    $config->setUpdatedAt(new \DateTime());
                    ++$updated;
                    break;
                case 'delete':
                    $this->entityManager->remove($config);
                    ++$deleted;
                    break;
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Bulk operation completed successfully',
            'action' => $action,
            'updated' => $updated,
            'deleted' => $deleted,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function exportConfiguration(): JsonResponse
    {
        $globalConfig = $this->configurationService->getGlobalAuditConfig();
        $entityConfigs = $this->entityConfigRepository->findAll();

        $export = [
            'global_config' => $this->serializeAuditConfig($globalConfig),
            'entity_configs' => [],
            'exported_at' => (new \DateTime())->format('c'),
            'version' => '1.0',
        ];

        foreach ($entityConfigs as $config) {
            $export['entity_configs'][] = $this->serializeEntityConfig($config, true);
        }

        return new JsonResponse($export);
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_CONFIG')]
    public function importConfiguration(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        $overwrite = $data['overwrite'] ?? false;
        $configData = $data['config'] ?? $data;

        if (!isset($configData['global_config']) || !isset($configData['entity_configs'])) {
            return new JsonResponse(['error' => 'Invalid configuration format'], Response::HTTP_BAD_REQUEST);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        try {
            $this->entityManager->beginTransaction();

            // Import global config
            $globalConfig = $this->configurationService->getGlobalAuditConfig();
            $globalConfigData = $configData['global_config'];

            if (isset($globalConfigData['enabled'])) {
                $globalConfig->setEnabled($globalConfigData['enabled']);
            }
            if (isset($globalConfigData['settings'])) {
                $globalConfig->setSettings($globalConfigData['settings']);
            }
            $globalConfig->setUpdatedAt(new \DateTime());

            // Import entity configs
            foreach ($configData['entity_configs'] as $entityConfigData) {
                if (!isset($entityConfigData['entity_class'])) {
                    $errors[] = 'Missing entity_class in configuration';
                    continue;
                }

                $entityClass = $entityConfigData['entity_class'];
                $existingConfig = $this->entityConfigRepository->findByEntityClass($entityClass);

                if ($existingConfig && !$overwrite) {
                    ++$skipped;
                    continue;
                }

                if (!$existingConfig) {
                    $existingConfig = new EntityConfig();
                    $existingConfig->setEntityClass($entityClass);
                    $existingConfig->setAuditConfig($globalConfig);
                    $existingConfig->setCreatedAt(new \DateTime());
                    $this->entityManager->persist($existingConfig);
                }

                $existingConfig->setEnabled($entityConfigData['enabled'] ?? true);
                $existingConfig->setCreateTable($entityConfigData['create_table'] ?? true);
                $existingConfig->setTableName($entityConfigData['table_name'] ?? null);
                $existingConfig->setIgnoredColumns($entityConfigData['ignored_columns'] ?? []);
                $existingConfig->setSettings($entityConfigData['settings'] ?? []);
                $existingConfig->setUpdatedAt(new \DateTime());

                ++$imported;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse([
                'message' => 'Configuration imported successfully',
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            return new JsonResponse([
                'error' => 'Import failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getStatus(): JsonResponse
    {
        $globalConfig = $this->configurationService->getGlobalAuditConfig();
        $entityConfigs = $this->entityConfigRepository->findAll();

        $enabledEntities = 0;
        $totalEntities = count($entityConfigs);

        foreach ($entityConfigs as $config) {
            if ($config->isEnabled()) {
                ++$enabledEntities;
            }
        }

        return new JsonResponse([
            'audit_enabled' => $globalConfig->isEnabled(),
            'total_entities' => $totalEntities,
            'enabled_entities' => $enabledEntities,
            'retention_days' => $this->configurationService->getRetentionDays(),
            'async_processing' => $this->configurationService->isAsyncProcessingEnabled(),
            'last_updated' => $globalConfig->getUpdatedAt()?->format('c'),
        ]);
    }

    private function serializeAuditConfig(AuditConfig $config): array
    {
        return [
            'id' => $config->getId(),
            'name' => $config->getName(),
            'enabled' => $config->isEnabled(),
            'settings' => $config->getSettings(),
            'created_at' => $config->getCreatedAt()->format('c'),
            'updated_at' => $config->getUpdatedAt()?->format('c'),
        ];
    }

    private function serializeEntityConfig(EntityConfig $config, bool $includeDetails = false): array
    {
        $data = [
            'id' => $config->getId(),
            'entity_class' => $config->getEntityClass(),
            'enabled' => $config->isEnabled(),
            'create_table' => $config->shouldCreateTable(),
            'table_name' => $config->getTableName(),
            'effective_table_name' => $config->getEffectiveTableName(),
            'ignored_columns' => $config->getIgnoredColumns(),
            'created_at' => $config->getCreatedAt()->format('c'),
            'updated_at' => $config->getUpdatedAt()?->format('c'),
        ];

        if ($includeDetails) {
            $data['settings'] = $config->getSettings();
            $data['audit_config_id'] = $config->getAuditConfig()->getId();
        }

        return $data;
    }
}
