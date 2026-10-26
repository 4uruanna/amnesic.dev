# Seul `To` donne accès, `Cc` et `Bcc` n'existent pas

Un compte voit un Message uniquement si l'une de ses adresses liées figure dans `To`. L'API ne porte ni `Cc` ni `Bcc` : ni dans le contrat, ni en base, ni dans les réponses. Les copies n'ont pas d'intérêt en V1, et ne pas les stocker évite de devoir les filtrer à chaque lecture.

Conséquence : une adresse en copie ne voit jamais le Message, même si elle fait l'objet d'un binding.
