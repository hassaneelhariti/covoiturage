# Journal des décisions

## 2026-11-17 — Réponses aux « questions à trancher »

**1. Une place libérée à une étape intermédiaire peut-elle être revendue pour la suite du trajet ?**
Oui. La disponibilité est suivie par tronçon : quand un passager descend à B, sa place est de nouveau libre sur B→C et
vendable à quelqu'un d'autre. Test : `TripAvailabilityTest::testFreedSeatAtIntermediateStopCanBeResold`.

**2. Comment se calcule le prix d'une portion ?**
Prix d'un tronçon = distance routière (OSRM, sinon estimation haversine × 1,25) × tarif au km du conducteur, arrondi au dirham
(minimum 1 MAD). Prix d'une portion = somme de ses tronçons, multiplié par le nombre de places. Arrondir par tronçon garantit
que la somme des portions égale le prix du trajet complet. Le tarif suggéré est de 0,60 MAD/km ; le conducteur peut le modifier.

**3. Que se passe-t-il quand le conducteur modifie un horaire ou supprime une étape après des réservations ?**
C'est refusé dès qu'une réservation confirmée existe (`TripService::assertStructureEditable`) : les passagers ont réservé un
horaire et un parcours précis. Le conducteur doit annuler le trajet (remboursement intégral de tous) puis le republier.
Avant toute réservation, la modification est libre. *Limite actuelle : l'interface ne propose pas encore l'édition d'un trajet
publié ; la règle est en place côté service.*

**4. Un passager peut-il réserver plusieurs places en une fois ?**
Oui, de 1 à 4 places par réservation, au même prix unitaire. Un passager ne peut avoir qu'une réservation active par trajet
(évite les doublons et les doubles clics) ; pour changer de portion, il annule et re-réserve.

**5. Qui note qui, et quand ?**
Uniquement après un trajet **terminé** (le conducteur le clôture après l'arrivée prévue) : chaque passager ayant effectivement
voyagé (réservation terminée) note le conducteur, et le conducteur note chacun de ses passagers. Les passagers ne se notent
pas entre eux. Un seul avis par couple auteur/destinataire et par trajet, note de 1 à 5. Les moyennes sont séparées par rôle.

## 2026-11-17 — Autres décisions

- **Annulation passager** (référence = heure de départ de *son* arrêt de montée) : ≥ 24 h → 100 %, 2 h à 24 h → 50 %, < 2 h → 0 %. Impossible après le départ.
- **Annulation conducteur** : possible avant le départ ; tous les passagers sont remboursés à 100 % ; le compteur `driverCancellations` augmente (affiché en back-office, sans sanction automatique).
- **Concurrence** : verrou pessimiste `SELECT … FOR UPDATE` sur la ligne du trajet dans une transaction, puis relecture des tronçons. Les opérations sur un même trajet sont sérialisées ; trajets différents = pas d'attente mutuelle. Filet de sécurité : contrainte CHECK en base.
- **Paiement** : simulé (entité `Payment`, statuts PAID / PARTIALLY_REFUNDED / REFUNDED). Aucune intégration externe.
- **Back-office** (EasyAdmin) : villes en CRUD complet ; utilisateurs (téléphone, rôle admin) modifiables ; véhicules, trajets, réservations, avis consultables, avis supprimables. Les trajets et réservations ne se modifient pas à la main : seules les règles métier changent leur état.
- **Fuseau** : Africa/Casablanca ; horaires saisis en heure locale, stockés sans fuseau.
- **API externes** : Nominatim (géocodage) et OSRM (itinéraire) via les serveurs publics de démonstration, avec User-Agent identifié, cache et repli sur une estimation en cas d'échec.
