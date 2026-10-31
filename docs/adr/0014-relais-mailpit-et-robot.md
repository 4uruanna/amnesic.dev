# Relais : Mailpit et un robot PHP dans `robot/`

Le Relais tourne sur la machine qui reçoit déjà le MX, dans deux conteneurs Docker. Mailpit, installé là le 2026-10-18, reçoit en SMTP, décode le MIME et garde chaque mail. Un robot PHP le relève par l'API REST de Mailpit et le dépose dans l'API : comme Mailpit fait tout le décodage, le robot ne fait que convertir le JSON de Mailpit en `MessageDeposit`. Le robot vit dans ce dépôt, sous `robot/`, en projet Composer à part, avec le `compose.yaml` des deux conteneurs. La CI ne le publie jamais sur la branche `ovh` ; il arrive sur la machine par un clone Git.

Cela remplace la dernière phrase de l'[ADR 0001](0001-reception-par-push.md) : le Relais n'est plus un projet séparé.

Options écartées : un Relais en Python (un second langage pour le même dépôt), un dépôt séparé (le contrat de dépôt et son seul client évolueraient chacun de leur côté), une image construite par la CI (un registre et un jeton de lecture de plus sur la machine).
