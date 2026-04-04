(() => {
    const factory = () => ({
        draggedOpportunityId: null,
        pressedOpportunityId: null,
        dragSuppressUntil: 0,
        dragGhostElement: null,

        primeCard(event, opportunityId) {
            if (event.button !== 0) {
                return;
            }

            this.pressedOpportunityId = opportunityId;
        },

        releaseCard() {
            if (this.draggedOpportunityId !== null) {
                return;
            }

            this.pressedOpportunityId = null;
        },

        startDrag(event, opportunityId) {
            this.draggedOpportunityId = opportunityId;
            this.pressedOpportunityId = opportunityId;
            this.dragSuppressUntil = Date.now() + 280;

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(opportunityId));
                this.createDragGhost(event);
            }

            document.body.classList.add('crm-kanban-is-dragging');
        },

        endDrag() {
            this.draggedOpportunityId = null;
            this.pressedOpportunityId = null;
            this.dragSuppressUntil = Date.now() + 180;

            this.destroyDragGhost();
            this.clearDropStates();
            document.body.classList.remove('crm-kanban-is-dragging');
        },

        dragOver(event) {
            event.currentTarget.classList.add('is-over');
        },

        dragLeave(event) {
            const relatedTarget = event.relatedTarget;

            if (! relatedTarget || ! event.currentTarget.contains(relatedTarget)) {
                event.currentTarget.classList.remove('is-over');
            }
        },

        drop(event, stageId, wire) {
            const rawValue = event.dataTransfer?.getData('text/plain') || this.draggedOpportunityId;
            const opportunityId = Number(rawValue);

            event.currentTarget.classList.remove('is-over');

            if (! opportunityId || ! stageId) {
                this.endDrag();
                return;
            }

            this.dragSuppressUntil = Date.now() + 320;
            wire.handleStageDrop(opportunityId, stageId);
            this.endDrag();
        },

        openCard(event, opportunityId, wire) {
            if ((this.draggedOpportunityId !== null) || (Date.now() < this.dragSuppressUntil)) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            wire.openDrawer(opportunityId);
        },

        cardClasses(opportunityId) {
            return {
                'is-pressing': this.pressedOpportunityId === opportunityId && this.draggedOpportunityId !== opportunityId,
                'is-dragging-source': this.draggedOpportunityId === opportunityId,
            };
        },

        clearDropStates() {
            document.querySelectorAll('.crm-kanban-stage-body.is-over').forEach((element) => {
                element.classList.remove('is-over');
            });
        },

        createDragGhost(event) {
            this.destroyDragGhost();

            const source = event.currentTarget;

            if (! source || ! event.dataTransfer) {
                return;
            }

            const ghost = source.cloneNode(true);
            const { width } = source.getBoundingClientRect();

            ghost.classList.add('crm-kanban-card-ghost');
            ghost.style.position = 'fixed';
            ghost.style.top = '-9999px';
            ghost.style.left = '-9999px';
            ghost.style.width = `${width}px`;

            document.body.appendChild(ghost);
            event.dataTransfer.setDragImage(ghost, 28, 28);

            this.dragGhostElement = ghost;
        },

        destroyDragGhost() {
            if (! this.dragGhostElement) {
                return;
            }

            this.dragGhostElement.remove();
            this.dragGhostElement = null;
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
        document.body.classList.remove('crm-kanban-is-dragging');
        document.querySelectorAll('.crm-kanban-stage-body.is-over').forEach((element) => {
            element.classList.remove('is-over');
        });
    });

    register();
})();
