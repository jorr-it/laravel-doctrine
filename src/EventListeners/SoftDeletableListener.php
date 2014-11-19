<?php namespace Mitch\LaravelDoctrine\EventListeners;

use Doctrine\ORM\Event\OnFlushEventArgs;
use DateTime;

class SoftDeletableListener
{
    public function onFlush(OnFlushEventArgs $event)
    {
        $entityManager = $event->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($this->isSoftDeletable($entity)) {
                $metadata = $entityManager->getClassMetadata(get_class($entity));
                $oldDeletedAt = $metadata->getFieldValue($entity, 'deletedAt');
                if ($oldDeletedAt instanceof DateTime) {
                    continue;
                }
                $now = new DateTime;
                $metadata->setFieldValue($entity, 'deletedAt', $now);
                $entityManager->persist($entity);

                $unitOfWork->propertyChanged($entity, 'deletedAt', $oldDeletedAt, $now);
                $unitOfWork->scheduleExtraUpdate($entity, array(
                    'deletedAt' => array($oldDeletedAt, $now)
                ));
            }
        }
    }

    private function isSoftDeletable($entity)
    {
        return false;
		//return array_key_exists('Mitch\LaravelDoctrine\Traits\SoftDeletes', class_uses($entity));
    }
}
