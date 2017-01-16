<?php

use PlentyConnector\Connector\ConfigService\ConfigServiceInterface;
use PlentyConnector\Connector\IdentityService\IdentityService;
use PlentyConnector\Connector\MappingService\MappingServiceInterface;
use PlentyConnector\Connector\TransferObject\Identity\Identity;
use PlentyConnector\Connector\TransferObject\MappedTransferObjectInterface;
use PlentyConnector\Connector\TransferObject\Mapping\MappingInterface;
use PlentyConnector\Installer\PermissionInstaller;
use PlentymarketsAdapter\Client\ClientInterface;

/**
 * Class Shopware_Controllers_Backend_PlentyConnector
 */
class Shopware_Controllers_Backend_PlentyConnector extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * initialize permissions per action
     */
    public function initAcl()
    {
        $this->addAclPermission('testApiCredentials', PermissionInstaller::PERMISSION_READ, 'Insufficient Permissions');

        $this->addAclPermission('getSettingsList', PermissionInstaller::PERMISSION_READ, 'Insufficient Permissions');
        $this->addAclPermission('saveSettings', PermissionInstaller::PERMISSION_WRITE, 'Insufficient Permissions');

        $this->addAclPermission('getMappingInformation', PermissionInstaller::PERMISSION_READ, 'Insufficient Permissions');
        $this->addAclPermission('updateIdentities', PermissionInstaller::PERMISSION_WRITE, 'Insufficient Permissions');

        $this->addAclPermission('syncItem', PermissionInstaller::PERMISSION_WRITE, 'Insufficient Permissions');
    }

    /**
     * @throws \Exception
     */
    public function testApiCredentialsAction()
    {
        /**
         * @var ClientInterface $client
         */
        $client = $this->container->get('plentmarkets_adapter.client');

        $params = [
            'username' => $this->Request()->get('ApiUsername'),
            'password' => $this->Request()->get('ApiPassword'),
        ];

        $options = [
            'base_uri' => $this->Request()->get('ApiUrl'),
        ];

        $success = false;

        try {
            $login = $client->request('POST', 'login', $params, null, null, $options);

            if (isset($login['accessToken'])) {
                $success = true;
            }
        } catch (Exception $e) {
            // fail silently
        }

        $this->View()->assign(array(
            'success' => $success,
        ));
    }

    /**
     * @throws \Exception
     */
    public function saveSettingsAction()
    {
        /**
         * @var ConfigServiceInterface $config
         */
        $config = $this->container->get('plentyconnector.config');

        $config->set('rest_url', $this->Request()->get('ApiUrl'));
        $config->set('rest_username', $this->Request()->get('ApiUsername'));
        $config->set('rest_password', $this->Request()->get('ApiPassword'));

        $this->View()->assign(array(
            'success' => true,
            'data' => $this->Request()->getParams(),
        ));
    }

    /**
     * @throws \Exception
     */
    public function getSettingsListAction()
    {
        $config = $this->container->get('plentyconnector.config');

        $this->View()->assign(array(
            'success' => true,
            'data' => [
                'ApiUrl' => $config->get('rest_url'),
                'ApiUsername' => $config->get('rest_username'),
                'ApiPassword' => $config->get('rest_password'),
            ],
        ));
    }

    /**
     * @throws \Exception
     */
    public function getMappingInformationAction()
    {
        $fresh = $this->request->get('fresh') === 'true';

        /**
         * @var MappingServiceInterface $mappingService
         */
        $mappingService = Shopware()->Container()->get('plentyconnector.mapping_service');

        try {
            $mappingInformation = $mappingService->getMappingInformation(null, $fresh);
        } catch(Exception $e) {
            $this->View()->assign([
                'success' => false,
                'message' => $e->getMessage()
            ]);

            return;
        }

        $transferObjectMapping = function (MappedTransferObjectInterface $object) {
            return [
                'identifier' => $object->getIdentifier(),
                'type' => $object::getType(),
                'name' => $object->getName()
            ];
        };

        $this->View()->assign([
            'success' => true,
            'data' => array_map(function (MappingInterface $mapping) use ($transferObjectMapping) {
                return [
                    'originAdapterName' => $mapping->getOriginAdapterName(),
                    'destinationAdapterName' => $mapping->getDestinationAdapterName(),
                    'originTransferObjects' => array_map($transferObjectMapping, $mapping->getOriginTransferObjects()),
                    'destinationTransferObjects' => array_map($transferObjectMapping, $mapping->getDestinationTransferObjects()),
                    'objectType' => $mapping->getObjectType()
                ];
            }, $mappingInformation)
        ]);
    }

    /**
     * @throws \Exception
     */
    public function updateIdentitiesAction()
    {
        $updates = json_decode($this->request->getRawBody());

        if (!is_array($updates)) {
            $updates = [$updates];
        }

        /**
         * @var IdentityService $identityService
         */
        $identityService = Shopware()->Container()->get('plentyconnector.identity_service');

        try {
            foreach ($updates as $update) {
                $originAdapterName = $update->originAdapterName;
                $originIdentifier = $update->originIdentifier;
                $destinationIdentifier = $update->identifier;
                $objectType = $update->objectType;

                $oldIdentity = $identityService->findOneBy([
                    'objectType' => $objectType,
                    'objectIdentifier' => $originIdentifier,
                    'adapterName' => $originAdapterName,
                ]);

                $originAdapterIdentifier = $oldIdentity->getAdapterIdentifier();

                $identityService->remove(Identity::fromArray([
                    'objectIdentifier' => $originIdentifier,
                    'objectType' => $objectType,
                    'adapterIdentifier' => $originAdapterIdentifier,
                    'adapterName' => $originAdapterName,
                ]));

                $identityService->create(
                    $destinationIdentifier,
                    $objectType,
                    $originAdapterIdentifier,
                    $originAdapterName
                );
            }

            $this->View()->assign([
                'success' => true,
                'data' => $updates
            ]);
        } catch(Exception $e) {
            $this->View()->assign([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * TODO: implement
     */
    public function syncItemAction()
    {
        $data = json_decode($this->request->getRawBody());

        if (null !== $data->itemId && '' !== $data->itemId) {
            $this->View()->assign([
                'success' => true
            ]);
        } else {
            $this->View()->assign([
                'success' => false,
                'message' => 'Artikel ID ist leer.'
            ]);
        }
    }
}