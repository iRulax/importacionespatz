<?php

namespace AmeliaBooking\Application\Services\TimeSlot;

use AmeliaBooking\Application\Services\Bookable\BookableApplicationService;
use AmeliaBooking\Application\Services\Booking\AppointmentApplicationService;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Location\Location;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Domain\ValueObjects\String\Status;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Google\GoogleCalendarService;
use AmeliaBooking\Infrastructure\Services\Outlook\OutlookCalendarService;
use DateTime;
use Exception;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class TimeSlotService
 *
 * @package AmeliaBooking\Application\Services\TimeSlot
 */
class TimeSlotService
{
    private $container;

    /**
     * TimeSlotService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param Collection $services
     * @param Collection $providers
     * @param Collection $locations
     * @param int        $serviceId
     * @param array      $providersIds
     * @param int        $isFrontEndBooking
     *
     * @return Collection
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function setSlotsEntities(
        $services,
        $providers,
        $locations,
        $serviceId,
        $providersIds,
        $isFrontEndBooking
    ) {
        /** @var ProviderApplicationService $providerAS */
        $providerAS = $this->container->get('application.user.provider.service');

        /** @var ServiceRepository $serviceRepository */
        $serviceRepository = $this->container->get('domain.bookable.service.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');


        /** @var Collection $fetchedServices */
        $fetchedServices = $serviceRepository->getAllArrayIndexedById();

        /** @var Service $service */
        foreach ($fetchedServices->getItems() as $service) {
            $services->addItem($service, $service->getId()->getValue());
        }


        $providersCriteria = [
            'providers'      => $providersIds,
        ];

        if ($isFrontEndBooking) {
            $providersCriteria['providerStatus'] = Status::VISIBLE;
        }

        /** @var Collection $fetchedProviders */
        $fetchedProviders = $providerRepository->getWithSchedule($providersCriteria);

        /** @var Provider $selectedProvider */
        foreach ($fetchedProviders->getItems() as $provider) {
            if ($provider->getServiceList()->keyExists($serviceId)) {
                $providers->addItem($provider, $provider->getId()->getValue());

                $providerAS->setProviderServices($provider, $services, false);
            }
        }


        /** @var Collection $fetchedLocations */
        $fetchedLocations = $locationRepository->getAllOrderedByName();

        /** @var Location $location */
        foreach ($fetchedLocations->getItems() as $location) {
            $locations->addItem($location, $location->getId()->getValue());
        }
    }

    /**
     * @param Service $service
     * @param array   $providerIds
     * @param int     $isFrontEndBooking
     *
     * @return Collection
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function getSlotsProviders(
        $service,
        $providerIds,
        $isFrontEndBooking
    ) {
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $providersCriteria = [
            'providers'      => $providerIds,
        ];

        if ($isFrontEndBooking) {
            $providersCriteria['providerStatus'] = Status::VISIBLE;
        }

        /** @var Collection $selectedProviders */
        $selectedProviders = $providerRepository->getWithSchedule($providersCriteria);

        /** @var Collection $providers */
        $providers = new Collection();

        /** @var Provider $selectedProvider */
        foreach ($selectedProviders->getItems() as $selectedProvider) {
            if ($selectedProvider->getServiceList()->keyExists($service->getId()->getValue())) {
                $providers->addItem($selectedProvider, $selectedProvider->getId()->getValue());
            }
        }

        return $providers;
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param Service    $service
     * @param Collection $services
     * @param Collection $providers
     * @param Collection $locations
     * @param int        $locationId
     * @param DateTime   $startDateTime
     * @param DateTime   $endDateTime
     * @param array      $selectedExtras
     * @param int        $excludeAppointmentId
     * @param int        $personsCount
     * @param int        $isFrontEndBooking
     *
     * @return array
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     * @throws ContainerException
     */
    public function getFreeSlots(
        $service,
        $services,
        $providers,
        $locations,
        $locationId,
        $startDateTime,
        $endDateTime,
        $selectedExtras,
        $excludeAppointmentId,
        $personsCount,
        $isFrontEndBooking
    ) {
        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');
        /** @var SettingsService $settingsDomainService */
        $settingsDomainService = $this->container->get('domain.settings.service');
        /** @var BookableApplicationService $bookableApplicationService */
        $bookableApplicationService = $this->container->get('application.bookable.service');
        /** @var AppointmentApplicationService $appointmentApplicationService */
        $appointmentApplicationService = $this->container->get('application.booking.appointment.service');
        /** @var ProviderApplicationService $providerApplicationService */
        $providerApplicationService = $this->container->get('application.user.provider.service');
        /** @var GoogleCalendarService $googleCalendarService */
        $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');
        /** @var OutlookCalendarService $outlookCalendarService */
        $outlookCalendarService = $this->container->get('infrastructure.outlook.calendar.service');

        $bookableApplicationService->checkServiceTimes($service);

        if (!$providers->length()) {
            return ['available' => [], 'occupied' => []];
        }

        try {
            // Remove Google Calendar Busy Slots
            $googleCalendarService->removeSlotsFromGoogleCalendar(
                $providers,
                $excludeAppointmentId,
                $startDateTime,
                $endDateTime
            );
        } catch (Exception $e) {
        }

        try {
            // Remove Outlook Calendar Busy Slots
            $outlookCalendarService->removeSlotsFromOutlookCalendar(
                $providers,
                $excludeAppointmentId,
                $startDateTime,
                $endDateTime
            );
        } catch (Exception $e) {
        }

        /** @var Collection $extras */
        $extras = $bookableApplicationService->filterServiceExtras(array_column($selectedExtras, 'id'), $service);

        $isGloballyBusySlot = $settingsDomainService->getSetting('appointments', 'isGloballyBusySlot');

        // check if Admin can book at any time & if it's dashboard
        $allowAdminToBookAtAnyTime = !$isFrontEndBooking &&
            ($loggedInUser = $this->container->get('logged.in.user')) &&
            $loggedInUser->getType() === AbstractUser::USER_ROLE_ADMIN &&
            $settingsDomainService->getSetting('roles', 'allowAdminBookAtAnyTime');


        /** @var Collection $futureAppointments */
        $futureAppointments = new Collection();

        $appointmentRepository->getFutureAppointments(
            $futureAppointments,
            $isGloballyBusySlot ? [] : $providers->keys(),
            DateTimeService::getCustomDateTimeObjectInUtc($startDateTime->format('Y-m-d H:i:s'))
                ->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            DateTimeService::getCustomDateTimeObjectInUtc($endDateTime->format('Y-m-d H:i:s'))->format('Y-m-d H:i:s')
        );

        $lastIndex = null;

        if ($excludeAppointmentId && $futureAppointments->keyExists($excludeAppointmentId)) {
            $futureAppointments->deleteItem($excludeAppointmentId);
        }

        $missingProvidersIds = [];

        /** @var Appointment $appointment */
        foreach ($futureAppointments->getItems() as $index => $appointment) {
            if (!$providers->keyExists($appointment->getProviderId()->getValue())) {
                $missingProvidersIds[$appointment->getProviderId()->getValue()] = true;
            }
        }

        /** @var Collection $missingProviders */
        $missingProviders = $missingProvidersIds ?
            $providerRepository->getWithSchedule(['providers' => array_keys($missingProvidersIds)]) : new Collection();

        /** @var Appointment $appointment */
        foreach ($futureAppointments->getItems() as $index => $appointment) {
            /** @var Provider $provider */
            $provider = $providers->keyExists($appointment->getProviderId()->getValue()) ?
                $providers->getItem($appointment->getProviderId()->getValue()) :
                $missingProviders->getItem($appointment->getProviderId()->getValue());

            /** @var Service $providerService */
            $providerService = $provider->getServiceList()->keyExists($appointment->getServiceId()->getValue()) ?
                $provider->getServiceList()->getItem($appointment->getServiceId()->getValue()) :
                $services->getItem($appointment->getServiceId()->getValue());

            $bookableApplicationService->checkServiceTimes($providerService);

            $appointment->setService($providerService);

            if ($lastIndex) {
                /** @var Appointment $previousAppointment */
                $previousAppointment = $futureAppointments->getItem($lastIndex);

                if ((
                    $previousAppointment->getLocationId() && $appointment->getLocationId() ?
                        $previousAppointment->getLocationId()->getValue() === $appointment->getLocationId()->getValue() : true
                    ) &&
                    $previousAppointment->getProviderId()->getValue() === $appointment->getProviderId()->getValue() &&
                    $previousAppointment->getServiceId()->getValue() === $appointment->getServiceId()->getValue() &&
                    $providerService->getMaxCapacity()->getValue() === 1 &&
                    $appointment->getBookingStart()->getValue()->format('H:i') !== '00:00' &&
                    $previousAppointment->getBookingEnd()->getValue()->format('Y-m-d H:i') ===
                    $appointment->getBookingStart()->getValue()->format('Y-m-d H:i')

                ) {
                    $previousAppointment->setBookingEnd(
                        new DateTimeValue(
                            DateTimeService::getCustomDateTimeObject(
                                $appointment->getBookingEnd()->getValue()->format('Y-m-d H:i:s')
                            )
                        )
                    );

                    $appointment->setStatus(new BookingStatus(BookingStatus::CANCELED));
                } else {
                    $lastIndex = $index;
                }
            } else {
                $lastIndex = $index;
            }
        }

        /** @var Collection $futureAppointmentsFiltered */
        $futureAppointmentsFiltered = new Collection();

        /** @var Appointment $appointment */
        foreach ($futureAppointments->getItems() as $index => $appointment) {
            if ($appointment->getStatus()->getValue() === BookingStatus::APPROVED ||
                $appointment->getStatus()->getValue() === BookingStatus::PENDING
            ) {
                $futureAppointmentsFiltered->addItem($appointment, $appointment->getId()->getValue());
            }
        }

        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        $eventApplicationService->removeSlotsFromEvents(
            $providers,
            [
                DateTimeService::getCustomDateTimeObject($startDateTime->format('Y-m-d H:i:s'))
                    ->modify('-10 day')
                    ->format('Y-m-d H:i:s'),
                DateTimeService::getCustomDateTimeObject($startDateTime->format('Y-m-d H:i:s'))
                    ->modify('+2 years')
                    ->format('Y-m-d H:i:s')
            ]
        );

        $providerApplicationService->addAppointmentsToAppointmentList($providers, $futureAppointmentsFiltered);

        if ($allowAdminToBookAtAnyTime) {
            $providerApplicationService->setProvidersAlwaysAvailableForAdmin($providers);
        }

        /** @var Provider $provider */
        foreach ($providers->getItems() as $provider) {
            /** @var Appointment $appointment */
            foreach ($provider->getAppointmentList()->getItems() as $index => $appointment) {
                if ($appointment->getService()) {
                    $bookableApplicationService->checkServiceTimes($appointment->getService());
                }
            }
        }

        return $this->getCalculatedFreeSlots(
            $startDateTime,
            $endDateTime,
            $providers,
            $locations,
            $service,
            $appointmentApplicationService->getAppointmentRequiredTime($service, $extras, $selectedExtras),
            $personsCount,
            $locationId,
            $allowAdminToBookAtAnyTime,
            $isFrontEndBooking
        );
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param Service  $service
     * @param DateTime $requiredDateTime
     * @param int       $providerId
     * @param array     $selectedExtras
     * @param int       $excludeAppointmentId
     * @param int       $personsCount
     * @param boolean   $isFrontEndBooking
     *
     * @return boolean
     * @throws QueryExecutionException
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws ContainerException
     * @throws Exception
     */
    public function isSlotFree(
        $service,
        $requiredDateTime,
        $providerId,
        $selectedExtras,
        $excludeAppointmentId,
        $personsCount,
        $isFrontEndBooking
    ) {
        $dateKey = $requiredDateTime->format('Y-m-d');
        $timeKey = $requiredDateTime->format('H:i');

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');

        /** @var Collection $services */
        $services = new Collection();

        /** @var Collection $providers */
        $providers = new Collection();

        /** @var Collection $locations */
        $locations = new Collection();

        $this->setSlotsEntities(
            $services,
            $providers,
            $locations,
            $service->getId()->getValue(),
            [$providerId],
            $isFrontEndBooking
        );

        $minimumBookingTimeInSeconds = $settingsDS
            ->getEntitySettings($service->getSettings())
            ->getGeneralSettings()
            ->getMinimumTimeRequirementPriorToBooking();

        $maximumBookingTimeInDays = $settingsDS
            ->getEntitySettings($service->getSettings())
            ->getGeneralSettings()
            ->getNumberOfDaysAvailableForBooking();

        $freeSlots = $this->getFreeSlots(
            $service,
            $services,
            $providers,
            $locations,
            null,
            $this->getMinimumDateTimeForBooking(
                '',
                $isFrontEndBooking,
                $minimumBookingTimeInSeconds
            ),
            $this->getMaximumDateTimeForBooking(
                '',
                $isFrontEndBooking,
                $maximumBookingTimeInDays
            ),
            $selectedExtras,
            $excludeAppointmentId,
            $personsCount,
            $isFrontEndBooking
        );

        return
            array_key_exists($dateKey, $freeSlots['available']) &&
            array_key_exists($timeKey, $freeSlots['available'][$dateKey]);
    }

    /**
     * @param string  $requiredBookingDateTimeString
     * @param boolean $isFrontEndBooking
     * @param string  $minimumTime
     *
     * @return DateTime
     * @throws Exception
     */
    public function getMinimumDateTimeForBooking($requiredBookingDateTimeString, $isFrontEndBooking, $minimumTime)
    {
        $requiredTimeOffset = $isFrontEndBooking ? $minimumTime : 0;

        $minimumBookingDateTime = DateTimeService::getNowDateTimeObject()->modify("+{$requiredTimeOffset} seconds");

        $requiredBookingDateTime = DateTimeService::getCustomDateTimeObject($requiredBookingDateTimeString);

        $minimumDateTime = ($minimumBookingDateTime > $requiredBookingDateTime ||
            $minimumBookingDateTime->format('Y-m-d') === $requiredBookingDateTime->format('Y-m-d')
        ) ? $minimumBookingDateTime : $requiredBookingDateTime->setTime(0, 0, 0);

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');

        $pastAvailableDays = $settingsDS->getSetting('general', 'backendSlotsDaysInPast');

        if (!$isFrontEndBooking && $pastAvailableDays) {
            $minimumDateTime->modify("-{$pastAvailableDays} days");
        }

        return $minimumDateTime;
    }

    /**
     * @param string  $requiredBookingDateTimeString
     * @param boolean $isFrontEndBooking
     * @param int     $maximumTime
     *
     * @return DateTime
     * @throws Exception
     */
    public function getMaximumDateTimeForBooking($requiredBookingDateTimeString, $isFrontEndBooking, $maximumTime)
    {
        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');

        $futureAvailableDays = $settingsDS->getSetting('general', 'backendSlotsDaysInFuture');

        $days = $maximumTime > $futureAvailableDays ?
            $maximumTime :
            $futureAvailableDays;

        $daysAvailableForBooking = $isFrontEndBooking ? $maximumTime : $days;

        $maximumBookingDateTime = DateTimeService::getNowDateTimeObject()->modify("+{$daysAvailableForBooking} day");

        $requiredBookingDateTime = $requiredBookingDateTimeString ?
            DateTimeService::getCustomDateTimeObject($requiredBookingDateTimeString) : $maximumBookingDateTime;

        return ($maximumBookingDateTime < $requiredBookingDateTime ||
            $maximumBookingDateTime->format('Y-m-d') === $requiredBookingDateTime->format('Y-m-d')
        ) ? $maximumBookingDateTime : $requiredBookingDateTime;
    }

    /**
     * @param DateTime   $startDateTime
     * @param DateTime   $endDateTime
     * @param Collection $providers
     * @param Collection $locations
     * @param Service    $service
     * @param int        $requiredTime
     * @param int        $personsCount
     * @param int        $locationId
     * @param bool       $allowAdminToBookAtAnyTime
     * @param bool       $isFrontEndBooking
     *
     * @return array
     * @throws Exception
     * @throws ContainerException
     */
    public function getCalculatedFreeSlots(
        $startDateTime,
        $endDateTime,
        $providers,
        $locations,
        $service,
        $requiredTime,
        $personsCount,
        $locationId,
        $allowAdminToBookAtAnyTime,
        $isFrontEndBooking
    ) {
        /** @var ProviderApplicationService $providerApplicationService */
        $providerApplicationService = $this->container->get('application.user.provider.service');

        /** @var \AmeliaBooking\Domain\Services\TimeSlot\TimeSlotService $timeSlotService */
        $timeSlotService = $this->container->get('domain.timeSlot.service');

        /** @var \AmeliaBooking\Application\Services\Settings\SettingsService $settingsApplicationService */
        $settingsApplicationService = $this->container->get('application.settings.service');

        /** @var SettingsService $settingsDomainService */
        $settingsDomainService = $this->container->get('domain.settings.service');

        $freeProvidersSlots = [];

        /** @var Provider $provider */
        foreach ($providers->getItems() as $provider) {
            $providerContainer = new Collection();

            if ($provider->getTimeZone()) {
                $providerApplicationService->modifyProviderTimeZone($provider, $startDateTime, $endDateTime);
            }

            $start = $provider->getTimeZone() ?
                DateTimeService::getCustomDateTimeObjectInTimeZone(
                    $startDateTime->format('Y-m-d H:i'),
                    $provider->getTimeZone()->getValue()
                ) : DateTimeService::getCustomDateTimeObject($startDateTime->format('Y-m-d H:i'));

            $end = $provider->getTimeZone() ?
                DateTimeService::getCustomDateTimeObjectInTimeZone(
                    $endDateTime->format('Y-m-d H:i'),
                    $provider->getTimeZone()->getValue()
                ) : DateTimeService::getCustomDateTimeObject($endDateTime->format('Y-m-d H:i'));

            $providerContainer->addItem($provider, $provider->getId()->getValue());

            $freeIntervals = $timeSlotService->getFreeTime(
                $service,
                $locationId,
                $locations,
                $providerContainer,
                $allowAdminToBookAtAnyTime || $provider->getTimeZone() ?
                    [] : $settingsApplicationService->getGlobalDaysOff(),
                $start,
                $end,
                $personsCount,
                $settingsDomainService->getSetting('appointments', 'allowBookingIfPending'),
                $settingsDomainService->getSetting('appointments', 'allowBookingIfNotMin'),
                $isFrontEndBooking ? $settingsDomainService->getSetting('appointments', 'openedBookingAfterMin') : false
            );

            $freeProvidersSlots[$provider->getId()->getValue()] = $timeSlotService->getAppointmentFreeSlots(
                $service,
                $requiredTime,
                $freeIntervals,
                $settingsDomainService->getSetting('general', 'timeSlotLength') ?: $requiredTime,
                $start,
                $settingsDomainService->getSetting('general', 'serviceDurationAsSlot'),
                $settingsDomainService->getSetting('general', 'bufferTimeInSlot'),
                true,
                $provider->getTimeZone() ?
                    $provider->getTimeZone()->getValue() : DateTimeService::getTimeZone()->getName()
            );
        }

        $freeSlots = [
            'available' => [],
            'occupied'  => [],
        ];

        foreach ($freeProvidersSlots as $providerKey => $providerSlots) {
            /** @var Provider $provider */
            $provider = $providers->getItem($providerKey);

            foreach (['available', 'occupied'] as $type) {
                if ($provider->getTimeZone()) {
                    $providerSlots[$type] = $timeSlotService->getSlotsInMainTimeZoneFromTimeZone(
                        $providerSlots[$type],
                        $provider->getTimeZone()->getValue()
                    );
                }

                foreach ($providerSlots[$type] as $dateKey => $dateSlots) {
                    foreach ($dateSlots as $timeKey => $slotData) {
                        if (empty($freeSlots[$type][$dateKey][$timeKey])) {
                            $freeSlots[$type][$dateKey][$timeKey] = [];
                        }

                        foreach ($slotData as $item) {
                            $freeSlots[$type][$dateKey][$timeKey][] = $item;
                        }

                        if (isset($freeSlots[$type][$dateKey])) {
                            if (!$freeSlots[$type][$dateKey]) {
                                unset($freeSlots[$type][$dateKey]);
                            } else {
                                ksort($freeSlots[$type][$dateKey]);
                            }
                        }
                    }
                }
            }
        }

        return $freeSlots;
    }
}
