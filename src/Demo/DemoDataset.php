<?php

namespace App\Demo;

use App\Enum\CompanyType as T;
use App\Enum\InteractionChannel as C;
use App\Enum\InteractionOutcome as O;
use App\Enum\PipelineStatus as S;
use App\Enum\Scope;
use App\Enum\SignalType;

/**
 * Données entièrement fictives de la démo (noms, personnes et domaines inventés, en `.example`).
 * Les jours sont relatifs à « maintenant » : interaction = [jours passés, canal, résultat, contact, origine, notes, prochaine action en jours],
 * signal = [jours passés, titre, source, type, données extraites].
 *
 * @phpstan-type Interaction array{0: int, 1: C, 2: O, 3: ?int, 4: ?string, 5: ?string, 6?: int}
 * @phpstan-type SignalRow array{0: int, 1: string, 2: string, 3: SignalType, 4?: array<string, mixed>}
 * @phpstan-type CompanyRow array{name: string, type: T, city: string, postalCode: string, size: string, naf?: string, scope?: Scope, status?: S, flags?: list<string>, notes?: string, contacts?: list<array{0: string, 1: string}>, interactions?: list<Interaction>, signals?: list<SignalRow>}
 */
final class DemoDataset
{
    /** @return list<CompanyRow> */
    public static function companies(): array
    {
        return [
            // Partenaires
            ['name' => 'Cobaltis Numérique', 'type' => T::Esn, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '20-49', 'naf' => '62.02A', 'status' => S::Partner,
                'notes' => 'Partenaire historique : missions régulières en sous-traitance Symfony.',
                'contacts' => [['Hélène Marchetti', 'Directrice des opérations'], ['Julien Brossard', 'Responsable technique']],
                'interactions' => [
                    [260, C::InPerson, O::Positive, 0, 'Salon du numérique', 'Premier échange sur le stand, intérêt pour du renfort Symfony.'],
                    [240, C::Email, O::MeetingScheduled, 0, null, 'Rendez-vous fixé dans leurs locaux.'],
                    [225, C::InPerson, O::Positive, 1, null, 'Cadrage de la première mission : refonte d\'une API.'],
                ]],
            ['name' => 'Pixelforge Studio', 'type' => T::WebAgency, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '6-9', 'naf' => '62.01Z', 'status' => S::Partner,
                'contacts' => [['Camille Rouvray', 'Cofondatrice']],
                'interactions' => [
                    [180, C::Phone, O::Positive, 0, 'Recommandation de Cobaltis', 'Besoin ponctuel sur du Nuxt.'],
                    [150, C::Email, O::Positive, 0, null, 'Devis accepté.'],
                ]],
            ['name' => 'Brumaire Consulting', 'type' => T::Esn, 'city' => 'Lyon', 'postalCode' => '69003', 'size' => '50-99', 'naf' => '62.02A', 'scope' => Scope::National, 'status' => S::Partner,
                'contacts' => [['Antoine Delcourt', 'Responsable des partenariats']],
                'interactions' => [
                    [120, C::Linkedin, O::Positive, 0, 'Offre freelance DevOps', 'Réponse rapide, mise en relation avec leur directeur technique.'],
                    [95, C::Phone, O::Positive, 0, null, 'Référencé comme sous-traitant.'],
                ]],

            // Rendez-vous
            ['name' => 'Doubs Systèmes d\'Information', 'type' => T::Esn, 'city' => 'Montbéliard', 'postalCode' => '25200', 'size' => '50-99', 'naf' => '62.02A',
                'contacts' => [['Nicolas Valmont', 'Directeur d\'agence'], ['Sonia Perrin', 'Assistante de direction']],
                'interactions' => [
                    [30, C::Phone, O::NobodyOnSite, null, null, 'Standard : rappeler en début de semaine.'],
                    [21, C::Phone, O::Positive, 0, null, 'Projets Symfony en cours, ouvert aux indépendants.'],
                    [6, C::Email, O::MeetingScheduled, 0, null, 'Visio proposée jeudi prochain.', 4],
                ]],
            ['name' => 'Studio Quartz', 'type' => T::WebAgency, 'city' => 'Dijon', 'postalCode' => '21000', 'size' => '10-19', 'naf' => '62.01Z',
                'contacts' => [['Léa Fontanel', 'Directrice de production']],
                'interactions' => [
                    [18, C::Linkedin, O::Positive, 0, 'Offre WordPress', 'Cherche un renfort WordPress / WooCommerce.'],
                    [9, C::Phone, O::MeetingScheduled, 0, null, 'Café prévu à Dijon.', -2],
                ],
                'signals' => [[30, 'Développeur WordPress / WooCommerce senior', 'linkedin', SignalType::JobPosting, ['location' => 'Dijon', 'workMode' => 'Hybride']]]],
            ['name' => 'Opaline Digital', 'type' => T::WebAgency, 'city' => 'Lyon', 'postalCode' => '69002', 'size' => '20-49', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Mathieu Garnier', 'CTO']],
                'interactions' => [
                    [14, C::Email, O::Positive, 0, 'Offre vue sur LinkedIn', 'Échange sur leur migration vers Nuxt.'],
                    [3, C::Phone, O::MeetingScheduled, 0, null, 'Visio mardi à 14 h.', 5],
                ],
                'signals' => [[20, 'Développeur Vue / Nuxt confirmé', 'hellowork', SignalType::JobPosting, ['city' => 'Lyon', 'contract' => 'CDI']]]],

            // À relancer
            ['name' => 'Belfort Digital Factory', 'type' => T::Esn, 'city' => 'Belfort', 'postalCode' => '90000', 'size' => '20-49', 'naf' => '62.02A',
                'contacts' => [['Romain Aubertin', 'Responsable des achats IT']],
                'interactions' => [
                    [40, C::Phone, O::Voicemail, 0, null, 'Messagerie.'],
                    [33, C::Email, O::Positive, 0, null, 'Demande une présentation et un tarif journalier.', -6],
                ]],
            ['name' => 'Bourgogne Logiciels', 'type' => T::Esn, 'city' => 'Dijon', 'postalCode' => '21000', 'size' => '20-49', 'naf' => '62.01Z',
                'contacts' => [['Élodie Vasseur', 'Directrice des ressources']],
                'interactions' => [
                    [27, C::Linkedin, O::Positive, 0, 'Recommandation de Studio Quartz', 'Projet Docker/K3s à venir à l\'automne.', -1],
                ],
                'signals' => [[25, 'DevOps Kubernetes / Docker', 'free-work', SignalType::FreelanceMission, ['city' => 'Dijon', 'dailyRate' => '500-600 €']]]],
            ['name' => 'Fluxane', 'type' => T::Esn, 'city' => 'Nantes', 'postalCode' => '44000', 'size' => '20-49', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Gaëlle Morvan', 'Business manager']],
                'interactions' => [
                    [16, C::Linkedin, O::Positive, 0, 'Offre freelance PHP', 'Intéressée, attend la validation d\'un budget.', 3],
                ]],
            ['name' => 'Rhénatech', 'type' => T::Esn, 'city' => 'Strasbourg', 'postalCode' => '67000', 'size' => '50-99', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Thibaut Weiss', 'Responsable recrutement']],
                'interactions' => [
                    [11, C::Email, O::Positive, 0, 'Offre Symfony', 'Renvoie vers le référencement fournisseurs.', 7],
                ]],

            // Contactées
            ['name' => 'Atelier Moirette', 'type' => T::WebAgency, 'city' => 'Dole', 'postalCode' => '39100', 'size' => '3-5', 'naf' => '62.01Z',
                'contacts' => [['Pauline Lacoste', 'Gérante']],
                'interactions' => [[8, C::Phone, O::NoAnswer, 0, null, 'Pas de réponse, mail envoyé ensuite.']]],
            ['name' => 'Jurassic Web', 'type' => T::WebAgency, 'city' => 'Lons-le-Saunier', 'postalCode' => '39000', 'size' => '6-9', 'naf' => '62.01Z',
                'contacts' => [['Baptiste Rey', 'Directeur']],
                'interactions' => [
                    [25, C::WebForm, O::Other, null, null, 'Formulaire de contact rempli.'],
                    [10, C::Phone, O::Voicemail, 0, null, 'Message laissé.', -3],
                ]],
            ['name' => 'Agence Lumeo', 'type' => T::ComAgency, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '10-19', 'naf' => '73.11Z',
                'contacts' => [['Inès Carpentier', 'Directrice de clientèle']],
                'interactions' => [[5, C::Email, O::Other, 0, 'Événement réseau', 'Mail de présentation envoyé.', 9]]],
            ['name' => 'Kerloc Conseil', 'type' => T::Esn, 'city' => 'Pontarlier', 'postalCode' => '25300', 'size' => '10-19', 'naf' => '62.02A',
                'contacts' => [['Yann Kerlot', 'Gérant']],
                'interactions' => [[35, C::Phone, O::NobodyOnSite, null, null, 'Fermé le vendredi.', -20]]],
            ['name' => 'Hexagone Interactive', 'type' => T::ComAgency, 'city' => 'Vesoul', 'postalCode' => '70000', 'size' => '3-5', 'naf' => '73.11Z',
                'contacts' => [['Margaux Bidault', 'Cofondatrice']],
                'interactions' => [[19, C::Linkedin, O::NoAnswer, 0, null, 'Invitation envoyée, pas de réponse.']]],
            ['name' => 'Maison Pixel', 'type' => T::WebAgency, 'city' => 'Lille', 'postalCode' => '59000', 'size' => '10-19', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Quentin Delattre', 'Lead développeur']],
                'interactions' => [[13, C::Linkedin, O::NoAnswer, 0, 'Offre Laravel', 'Message envoyé suite à leur annonce.']],
                'signals' => [[20, 'Développeur Laravel / Vue', 'linkedin', SignalType::JobPosting, ['location' => 'Lille', 'workMode' => 'Sur site']]]],
            ['name' => 'Garonne Web', 'type' => T::WebAgency, 'city' => 'Toulouse', 'postalCode' => '31000', 'size' => '6-9', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Salomé Dupré', 'Fondatrice']],
                'interactions' => [[22, C::Email, O::NoAnswer, 0, 'Offre WordPress', 'Premier mail.', -8]],
                'signals' => [[25, 'Développeur WordPress freelance', 'free-work', SignalType::FreelanceMission, ['city' => 'Toulouse', 'dailyRate' => '400-480 €']]]],

            // En attente
            ['name' => 'Nuage Comtois', 'type' => T::Esn, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '6-9', 'naf' => '63.11Z',
                'contacts' => [['Vincent Tournier', 'Directeur']],
                'interactions' => [[210, C::Phone, O::JustHired, 0, null, 'Vient d\'embaucher un développeur.']],
                'signals' => [[30, 'Développeur PHP / Symfony', 'hellowork', SignalType::JobPosting, ['city' => 'Besançon', 'contract' => 'CDI']]]],
            ['name' => 'Carbone Studio', 'type' => T::ComAgency, 'city' => 'Paris', 'postalCode' => '75011', 'size' => '10-19', 'naf' => '73.11Z', 'scope' => Scope::National,
                'contacts' => [['Alix Moreau', 'Directrice de création']],
                'interactions' => [[190, C::Linkedin, O::NoNeed, 0, null, 'Pas de besoin avant la rentrée.']]],
            ['name' => 'Océane Conseil', 'type' => T::Esn, 'city' => 'Bordeaux', 'postalCode' => '33000', 'size' => '20-49', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Fabien Lasserre', 'Responsable d\'agence']],
                'interactions' => [[45, C::Phone, O::JustHired, 0, null, 'Équipe au complet jusqu\'à l\'année prochaine.']]],
            ['name' => 'Tandem Interactive', 'type' => T::WebAgency, 'city' => 'Paris', 'postalCode' => '75010', 'size' => '10-19', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Noémie Sauvage', 'Cheffe de projet']],
                'interactions' => [[60, C::Email, O::NoNeed, 0, null, 'Sous-traitance gérée en interne.']]],
            ['name' => 'Lorraine Data Services', 'type' => T::Esn, 'city' => 'Nancy', 'postalCode' => '54000', 'size' => '20-49', 'naf' => '62.03Z', 'scope' => Scope::National,
                'contacts' => [['Cédric Hanus', 'Directeur technique']],
                'interactions' => [[30, C::Phone, O::NoNeed, 0, null, 'Pas de besoin PHP, seulement du Java.']]],

            // Exclues
            ['name' => 'Altaïr Software Services', 'type' => T::Esn, 'city' => 'Paris', 'postalCode' => '75008', 'size' => '100-199', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Sandrine Lefort', 'Responsable des achats']],
                'interactions' => [[70, C::Phone, O::NoFreelancePolicy, 0, null, 'Aucun freelance, uniquement des salariés.']]],
            ['name' => 'Vektor Agency', 'type' => T::ComAgency, 'city' => 'Lyon', 'postalCode' => '69001', 'size' => '6-9', 'naf' => '73.11Z', 'scope' => Scope::National,
                'contacts' => [['Hugo Blanchet', 'Directeur']],
                'interactions' => [[55, C::Email, O::NoFreelancePolicy, 0, null, 'Politique : pas de prestataires indépendants.']]],

            // À contacter, avec offres récentes (priorités)
            ['name' => 'Silex Digital', 'type' => T::WebAgency, 'city' => 'Annecy', 'postalCode' => '74000', 'size' => '10-19', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Marion Berthet', 'Responsable des projets']],
                'signals' => [
                    [2, 'Développeur Symfony / Vue.js en télétravail', 'linkedin', SignalType::JobPosting, ['location' => 'Annecy', 'workMode' => 'À distance', 'remote' => true]],
                    [9, 'Développeur Nuxt freelance', 'free-work', SignalType::FreelanceMission, ['city' => 'Annecy', 'dailyRate' => '450-550 €', 'remote' => true]],
                ]],
            ['name' => 'Nodale', 'type' => T::Esn, 'city' => 'Grenoble', 'postalCode' => '38000', 'size' => '50-99', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Lucas Armand', 'Responsable recrutement']],
                'signals' => [[4, 'Ingénieur DevOps Docker / Kubernetes', 'hellowork', SignalType::JobPosting, ['city' => 'Grenoble', 'contract' => 'CDI']]]],
            ['name' => 'Sillage Numérique', 'type' => T::WebAgency, 'city' => 'Rennes', 'postalCode' => '35000', 'size' => '6-9', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Clara Lemoine', 'Gérante']],
                'signals' => [[25, 'Développeur WordPress / PHP', 'linkedin', SignalType::JobPosting, ['location' => 'Rennes', 'workMode' => 'Hybride']]]],
            ['name' => 'Orchis Digital', 'type' => T::WebAgency, 'city' => 'Mulhouse', 'postalCode' => '68100', 'size' => '10-19', 'naf' => '62.01Z', 'scope' => Scope::National,
                'contacts' => [['Damien Schmitt', 'Directeur technique']],
                'signals' => [[40, 'Développeur Symfony confirmé', 'free-work', SignalType::JobPosting, ['city' => 'Mulhouse', 'contracts' => ['Freelance', 'CDI']]]]],
            ['name' => 'Quasar Partners', 'type' => T::Esn, 'city' => 'Paris', 'postalCode' => '75002', 'size' => '100-199', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Aurélien Perret', 'Manager de practice']],
                'signals' => [[35, 'Tech Lead PHP / Symfony', 'linkedin', SignalType::JobPosting, ['location' => 'Paris', 'workMode' => 'Sur site']]]],
            ['name' => 'Ardoise Studio', 'type' => T::WebAgency, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '3-5', 'naf' => '62.01Z',
                'contacts' => [['Juliette Cornu', 'Cofondatrice']],
                'signals' => [[40, 'Développeur Vue.js junior / confirmé', 'hellowork', SignalType::JobPosting, ['city' => 'Besançon', 'contract' => 'CDI']]]],

            // À contacter, sans signal
            ['name' => 'Les Ateliers du Pixel', 'type' => T::WebAgency, 'city' => 'Morteau', 'postalCode' => '25500', 'size' => '3-5', 'naf' => '62.01Z',
                'contacts' => [['Mélanie Cattin', 'Gérante']]],
            ['name' => 'Fiscalis Éditions', 'type' => T::Unknown, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '10-19', 'naf' => '58.29C',
                'notes' => 'Éditeur de logiciels : type à confirmer (éditeur ou ESN ?).'],
            ['name' => 'Lumen & Co', 'type' => T::ComAgency, 'city' => 'Dijon', 'postalCode' => '21000', 'size' => '6-9', 'naf' => '73.11Z',
                'contacts' => [['Sacha Pellegrin', 'Directeur de création']]],
            ['name' => 'Marelle Studio', 'type' => T::WebAgency, 'city' => 'Nancy', 'postalCode' => '54000', 'size' => '3-5', 'naf' => '62.01Z', 'scope' => Scope::National],
            ['name' => 'Cimes Informatique', 'type' => T::Esn, 'city' => 'Chambéry', 'postalCode' => '73000', 'size' => '20-49', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Raphaël Joly', 'Directeur commercial']]],
            ['name' => 'Dolmen Systèmes', 'type' => T::Esn, 'city' => 'Rennes', 'postalCode' => '35000', 'size' => '50-99', 'naf' => '62.02A', 'scope' => Scope::National],
            ['name' => 'Tramontane IT', 'type' => T::Esn, 'city' => 'Marseille', 'postalCode' => '13001', 'size' => '20-49', 'naf' => '62.02A', 'scope' => Scope::National,
                'contacts' => [['Estelle Giraud', 'Responsable des opérations']]],
            ['name' => 'Sequana Tech', 'type' => T::Esn, 'city' => 'Dijon', 'postalCode' => '21000', 'size' => '10-19', 'naf' => '62.02A', 'flags' => ['IN_DIFFICULTY'],
                'notes' => 'Redressement judiciaire signalé dans la presse locale.'],
            ['name' => 'Atelier Brume', 'type' => T::WebAgency, 'city' => 'Montpellier', 'postalCode' => '34000', 'size' => '3-5', 'naf' => '62.01Z', 'scope' => Scope::National, 'flags' => ['MAYBE_INACTIVE'],
                'notes' => 'Site inaccessible, dernière actualité il y a deux ans.'],
            ['name' => 'Mécanique Vauban', 'type' => T::EndClient, 'city' => 'Besançon', 'postalCode' => '25000', 'size' => '50-99',
                'notes' => 'Client final : hors cible, gardé pour mémoire.'],
        ];
    }

    /**
     * Offres sans entreprise rattachée : une partie « à vérifier », une partie écartée.
     *
     * @return list<array{0: int, 1: string, 2: string, 3: SignalType, 4: array<string, mixed>, 5?: bool}> le dernier élément indique une offre écartée
     */
    public static function unresolvedSignals(): array
    {
        return [
            [1, 'Développeur Full Stack PHP / Symfony', 'linkedin', SignalType::JobPosting, ['companyName' => 'Cabinet Vertigo', 'location' => 'Lyon', 'workMode' => 'Hybride']],
            [1, 'Développeur Freelance', 'linkedin', SignalType::FreelanceMission, ['companyName' => 'Agence Nautilus', 'location' => 'France', 'workMode' => 'À distance', 'remote' => true, 'toReview' => true]],
            [2, 'Développeur Web Fullstack Confirmé', 'hellowork', SignalType::JobPosting, ['companyName' => 'Studio Marguerite', 'city' => 'Paris', 'toReview' => true]],
            [3, 'Lead Dev Vue / Nuxt', 'free-work', SignalType::FreelanceMission, ['city' => 'Île-de-France', 'dailyRate' => '500-600 €', 'durationMonths' => 6]],
            [4, 'DevOps Docker / K3s', 'free-work', SignalType::FreelanceMission, ['city' => 'Toulouse', 'dailyRate' => '450-520 €', 'remote' => true]],
            [5, 'Développeur Drupal PHP', 'hellowork', SignalType::JobPosting, ['companyName' => 'Digitalis Conseil', 'city' => 'Paris', 'toReview' => true]],
            [6, 'Développeur Symfony expérimenté', 'linkedin', SignalType::JobPosting, ['companyName' => 'Éclat Numérique', 'location' => 'Nancy', 'workMode' => 'Sur site']],
            [8, 'Ingénieur développement logiciel', 'hellowork', SignalType::JobPosting, ['companyName' => 'Ferrand & Fils', 'city' => 'Lyon', 'toReview' => true]],
            [10, 'Développeur Mainframe Cobol', 'hellowork', SignalType::JobPosting, ['companyName' => 'Banque Aurore', 'city' => 'Lyon'], true],
            [12, 'Développeur Java Spring', 'free-work', SignalType::FreelanceMission, ['city' => 'Paris'], true],
            [15, 'Développeur Power BI', 'hellowork', SignalType::JobPosting, ['companyName' => 'Groupe Anémone', 'city' => 'Paris'], true],
        ];
    }
}
