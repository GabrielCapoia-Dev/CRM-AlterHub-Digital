(() => {
    const factory = () => ({
        draggedOpportunityId: null,

        startDrag(event, opportunityId) {
            this.draggedOpportunityId = opportunityId;

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(opportunityId));
        },

        endDrag() {
            this.draggedOpportunityId = null;
            this.clearDropStates();
        },

        dragOver(event) {
            event.currentTarget.classList.add('is-over');
        },

        dragLeave(event) {
            if (! event.currentTarget.contains(event.relatedTarget)) {
                event.currentTarget.classList.remove('is-over');
            }
        },

        drop(event, stageId, wire) {
            const rawValue = event.dataTransfer.getData('text/plain') || this.draggedOpportunityId;
            const opportunityId = Number(rawValue);

            event.currentTarget.classList.remove('is-over');

            if (! opportunityId || ! stageId) {
                return;
            }

            wire.handleStageDrop(opportunityId, stageId);
            this.draggedOpportunityId = null;
        },

        clearDropStates() {
            document.querySelectorAll('.crm-kanban-stage-body.is-over').forEach((element) => {
                element.classList.remove('is-over');
            });
        },
    });

    const register = () => {
        if (! window.Alpine) {
            return;
        }

        window.Alpine.data('crmKanbanBoard', factory);
    };

    document.addEventListener('alpine:init', register, { once: true });
    document.addEventListener('livewire:navigated', () => {
        document.querySelectorAll('.crm-kanban-stage-body.is-over').forEach((element) => {
            element.classList.remove('is-over');
        });
    });

    register();
})();
