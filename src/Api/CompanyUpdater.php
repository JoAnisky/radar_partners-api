<?php

namespace App\Api;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Enum\PipelineStatus;
use App\Enum\Scope;
use App\Repository\CompanyRepository;
use App\Service\PipelineStatusResolver;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Modification partielle d'une entreprise : seuls les champs présents dans le JSON sont touchés. */
final readonly class CompanyUpdater
{
    private const FIELDS = [
        'status', 'type', 'scope', 'notes', 'recontactAfter', 'flags', 'archived',
        'name', 'website', 'address', 'postalCode', 'city', 'phone', 'email', 'siren', 'nafCode', 'headcountRange',
    ];

    public function __construct(
        private PipelineStatusResolver $statusResolver,
        private CompanyRepository $companies,
    ) {
    }

    /** @param array<mixed> $payload */
    public function update(Company $company, array $payload, \DateTimeImmutable $now): void
    {
        $unknown = array_diff(array_map('strval', array_keys($payload)), self::FIELDS);
        if ([] !== $unknown) {
            throw new UnprocessableEntityHttpException(sprintf('Champ inconnu : %s', implode(', ', $unknown)));
        }

        $this->updateIdentity($company, $payload);

        if (\array_key_exists('status', $payload)) {
            $status = Payload::enum(PipelineStatus::class, $payload['status'], 'status');
            $changed = $status !== $company->getStatus();
            $company->setStatus($status);
            if ($changed && !\array_key_exists('recontactAfter', $payload)) {
                $company->setRecontactAfter(PipelineStatus::OnHold === $status ? $this->statusResolver->onHoldUntil($now) : null);
            }
        }

        if (\array_key_exists('recontactAfter', $payload)) {
            $company->setRecontactAfter(Payload::nullableDate($payload['recontactAfter'], 'recontactAfter'));
        }

        if (\array_key_exists('type', $payload)) {
            $company->setType(Payload::enum(CompanyType::class, $payload['type'], 'type'));
        }

        if (\array_key_exists('scope', $payload)) {
            $company->setScope(Payload::enum(Scope::class, $payload['scope'], 'scope'));
        }

        if (\array_key_exists('notes', $payload)) {
            $company->setNotes(Payload::nullableString($payload['notes'], 'notes', 10000));
        }

        if (\array_key_exists('flags', $payload)) {
            $company->setFlags(self::flags($payload['flags']));
        }

        if (\array_key_exists('archived', $payload)) {
            if (!\is_bool($payload['archived'])) {
                throw new UnprocessableEntityHttpException('archived doit être un booléen.');
            }
            $payload['archived'] ? $company->archive() : $company->unarchive();
        }
    }

    /** @param array<mixed> $payload */
    private function updateIdentity(Company $company, array $payload): void
    {
        if (\array_key_exists('name', $payload)) {
            $name = Payload::nullableString($payload['name'], 'name', 255) ?? throw new UnprocessableEntityHttpException('Le nom est obligatoire.');
            $existing = $this->companies->findOneByName($name);
            if (null !== $existing && $existing !== $company) {
                throw new ConflictHttpException('Une entreprise porte déjà ce nom.');
            }
            $company->setName($name);
        }

        if (\array_key_exists('website', $payload)) {
            $website = Payload::nullableString($payload['website'], 'website', 255);
            $company->setWebsite(null === $website || 1 === preg_match('#^https?://#i', $website) ? $website : 'https://'.$website);
        }

        if (\array_key_exists('address', $payload)) {
            $company->setAddress(Payload::nullableString($payload['address'], 'address', 255));
        }

        if (\array_key_exists('postalCode', $payload)) {
            $company->setPostalCode(Payload::nullableString($payload['postalCode'], 'postalCode', 10));
        }

        if (\array_key_exists('city', $payload)) {
            $company->setCity(Payload::nullableString($payload['city'], 'city', 255));
        }

        if (\array_key_exists('headcountRange', $payload)) {
            $company->setHeadcountRange(Payload::nullableString($payload['headcountRange'], 'headcountRange', 50));
        }

        if (\array_key_exists('phone', $payload)) {
            try {
                $company->setPhone(Payload::nullableString($payload['phone'], 'phone', 30));
            } catch (\InvalidArgumentException) {
                throw new UnprocessableEntityHttpException('Numéro de téléphone invalide.');
            }
        }

        if (\array_key_exists('email', $payload)) {
            $email = Payload::nullableString($payload['email'], 'email', 255);
            if (null !== $email && false === filter_var($email = strtolower($email), \FILTER_VALIDATE_EMAIL)) {
                throw new UnprocessableEntityHttpException('Adresse email invalide.');
            }
            $company->setEmail($email);
        }

        if (\array_key_exists('siren', $payload)) {
            $siren = preg_replace('/\s+/', '', Payload::nullableString($payload['siren'], 'siren', 20) ?? '');
            if ('' !== $siren && 1 !== preg_match('/^\d{9}$/', (string) $siren)) {
                throw new UnprocessableEntityHttpException('Le SIREN doit contenir 9 chiffres.');
            }
            $existing = '' === $siren ? null : $this->companies->findOneBy(['siren' => $siren]);
            if (null !== $existing && $existing !== $company) {
                throw new ConflictHttpException('Ce SIREN est déjà utilisé par une autre entreprise.');
            }
            $company->setSiren('' === $siren ? null : $siren);
        }

        if (\array_key_exists('nafCode', $payload)) {
            $naf = Payload::nullableString($payload['nafCode'], 'nafCode', 10);
            if (null !== $naf && 1 !== preg_match('/^\d{2}\.\d{2}[A-Z]$/', $naf = strtoupper($naf))) {
                throw new UnprocessableEntityHttpException('Le code NAF doit ressembler à 62.01Z.');
            }
            $company->setNafCode($naf);
        }
    }

    /** @return list<string> */
    private static function flags(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new UnprocessableEntityHttpException('flags doit être une liste.');
        }

        $flags = [];
        foreach ($value as $flag) {
            if (!\is_string($flag) || 1 !== preg_match('/^[A-Z0-9_]{1,40}$/', $flag)) {
                throw new UnprocessableEntityHttpException('Chaque flag doit être en majuscules (A-Z, 0-9, _), 40 caractères maximum.');
            }
            $flags[] = $flag;
        }

        return $flags;
    }
}
