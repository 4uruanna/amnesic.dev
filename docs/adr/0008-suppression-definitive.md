# La suppression d'un Message est définitive pour tous

`DELETE /v1/messages/{id}` efface le Message (métadonnées et fichiers) pour tous les Comptes, même quand d'autres Comptes y ont accès par leurs propres Bindings. Un masquage par Compte demanderait une table de plus et une purge différée, pour un cas rare en V1.

Conséquence : un Compte `user` peut faire disparaître un Message qu'un autre Compte n'a pas encore lu.
