import PublicLayout from '@/Layouts/PublicLayout';
import Text from '@/Components/Text';
import { Head, Link } from '@inertiajs/react';
import {
    Fuel, Gauge, Mountain, PhoneCall, ShieldAlert, Signpost, TrafficCone,
} from 'lucide-react';

/**
 * "Conduire au Maroc" — a French informational/SEO page (the market's #1
 * organic acquisition channel for car rental). Deliberately written in French
 * (the storefront's default + target-market language); it answers the exact
 * questions Moroccan-rental searches surface: speed limits, péages, fuel,
 * gendarmerie checks, zero-alcohol rule, emergency numbers, mountain/desert
 * advice. All colors/spacing via theme tokens (Hard Rule 3).
 */

const sections = [
    {
        icon: Gauge,
        title: 'Limites de vitesse',
        items: [
            'En ville : 60 km/h (souvent réduit à 40 km/h près des écoles).',
            'Route nationale : 100 km/h.',
            'Autoroute : 120 km/h.',
            'Les radars sont fréquents, fixes et mobiles, en particulier sur les grands axes.',
        ],
    },
    {
        icon: Signpost,
        title: 'Péages',
        items: [
            'Environ 0,07 DH/km sur autoroute.',
            'Comptez environ 70 MAD pour un trajet Marrakech → Agadir.',
            'Paiement en espèces ou par carte accepté à chaque barrière.',
        ],
    },
    {
        icon: Fuel,
        title: 'Carburant',
        items: [
            'Essence : environ 14-15 DH/litre.',
            'Diesel : environ 13-14 DH/litre.',
            'Les stations-service sont nombreuses sur les grands axes ; faites le plein avant les zones rurales ou montagneuses.',
        ],
    },
    {
        icon: ShieldAlert,
        title: 'Contrôles de gendarmerie',
        items: [
            'Ayez toujours avec vous : permis de conduire, passeport ou carte d\'identité, carte grise et contrat de location.',
            'Les contrôles sont fréquents aux abords des grandes villes et sur les autoroutes.',
            'Présentez calmement vos documents — les contrôles sont rapides lorsque tout est en règle.',
        ],
    },
    {
        icon: TrafficCone,
        title: 'Règles locales',
        items: [
            'Zéro alcool au volant : tolérance zéro, sanction sévère.',
            'Ceinture de sécurité obligatoire pour tous les passagers.',
            'Téléphone au volant interdit sans kit mains-libres.',
            'Les enfants doivent être installés dans un siège adapté à leur âge et leur poids.',
        ],
    },
    {
        icon: PhoneCall,
        title: 'Numéros d\'urgence',
        items: [
            'Police : 19',
            'Gendarmerie Royale : 177',
            'SAMU (ambulance) : 15',
            'Pompiers : 15',
        ],
    },
    {
        icon: Mountain,
        title: 'Conseils pour la montagne et le désert',
        items: [
            'Les pistes non goudronnées sont réservées aux véhicules 4x4 — un véhicule classique n\'est pas couvert hors route.',
            'En montagne (Atlas), les routes peuvent être étroites et glissantes ; prévoyez des chaînes en hiver.',
            'Dans le désert, emportez de l\'eau et faites le plein avant de quitter les grands axes.',
            'Renseignez-vous sur l\'état des routes avant un long trajet (surtout après de fortes pluies).',
        ],
    },
];

export default function DrivingInMorocco() {
    return (
        <PublicLayout>
            <Head title="Conduire au Maroc — Informations pratiques" />

            <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
                {/* Intro */}
                <Text variant="h1">Conduire au Maroc — Informations pratiques</Text>
                <Text variant="body-lg" className="mt-4 text-textMuted">
                    Avant de prendre la route au Maroc, voici l'essentiel à savoir :
                    limites de vitesse, péages, carburant, contrôles de gendarmerie
                    et règles locales. Roulez en toute confiance.
                </Text>

                {/* Info cards */}
                <div className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {sections.map((section) => {
                        const Icon = section.icon;

                        return (
                            <section
                                key={section.title}
                                className="rounded-container border border-border bg-surface p-6 shadow-resting"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Icon className="h-5 w-5" aria-hidden="true" />
                                    </span>
                                    <Text variant="h3">{section.title}</Text>
                                </div>
                                <ul className="mt-4 space-y-2.5">
                                    {section.items.map((item) => (
                                        <li key={item} className="flex items-start gap-2 text-sm text-text">
                                            <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-secondary" aria-hidden="true" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        );
                    })}
                </div>

                {/* CTA band */}
                <div className="mt-10 rounded-container bg-primary px-6 py-10 text-center">
                    <Text variant="h2" className="text-onPrimary">
                        Prêt à explorer le Maroc ?
                    </Text>
                    <p className="mx-auto mt-3 max-w-xl text-onPrimary/80">
                        Choisissez le véhicule adapté à votre voyage — citadine,
                        SUV ou 4x4 pour les pistes.
                    </p>
                    <Link
                        href={route('vehicles.index')}
                        className="mt-6 inline-block rounded-interactive bg-secondary px-6 py-3 font-body font-semibold text-onSecondary transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                    >
                        Parcourir notre flotte
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
