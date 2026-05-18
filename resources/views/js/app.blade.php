function crudStudio() {
    return {
        // On injecte toutes les variables et fonctions de navigation.js
        ...navigationModule,
        init() {
            console.log('Félicitations, l\'étape Navigation est active !');
        }
    };
}