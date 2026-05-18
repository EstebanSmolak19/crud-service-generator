const navigationModule = {
    activeTab: 'controllers', // L'onglet sélectionné par défaut dans la sidebar
    selectedController: null, // Stockera l'objet du contrôleur sur lequel tu as cliqué

    selectController(controller) {
        this.selectedController = controller;
        console.log('Contrôleur sélectionné avec succès :', controller);
    }
};
