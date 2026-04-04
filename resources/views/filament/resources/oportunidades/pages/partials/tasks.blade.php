@if (! $selectedOpportunity)
    <div class="crm-drawer-empty">Salve a oportunidade para controlar tarefas e follow-ups.</div>
@else
    <div class="crm-drawer-stack">
        <div class="crm-entity-list">
            @forelse ($selectedOpportunity->oportunidadeTarefas as $task)
                <article class="crm-entity-card" wire:key="task-{{ $task->id }}">
                    <div>
                        <h4>{{ $task->titulo }}</h4>
                        <p>{{ \App\Models\OportunidadeTarefa::statusOptions()[$task->status] ?? $task->status }} · {{ $task->user?->name ?? 'Sem responsável' }}</p>
                        <small>{{ $task->data_prevista?->format('d/m/Y') ?? 'Sem data prevista' }}</small>
                    </div>

                    <div class="crm-entity-actions">
                        @can('update', $task)
                            <button type="button" class="crm-btn crm-btn-secondary" wire:click="editTask({{ $task->id }})">
                                Editar
                            </button>
                        @endcan

                        @can('delete', $task)
                            <button type="button" class="crm-btn crm-btn-danger" wire:click="deleteTask({{ $task->id }})">
                                Excluir
                            </button>
                        @endcan
                    </div>
                </article>
            @empty
                <div class="crm-drawer-empty">Nenhuma tarefa registrada nesta oportunidade.</div>
            @endforelse
        </div>

        @if (auth()->user()?->can('create', \App\Models\OportunidadeTarefa::class) || filled($taskForm['id']))
            <form class="crm-drawer-form" wire:submit.prevent="saveTask">
                <div class="crm-drawer-grid">
                    <label class="crm-field">
                        <span>Responsável</span>
                        <select wire:model.defer="taskForm.user_id">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner['id'] }}">{{ $owner['nome'] }}</option>
                            @endforeach
                        </select>
                        @error('taskForm.user_id')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field">
                        <span>Status</span>
                        <select wire:model.defer="taskForm.status">
                            @foreach (\App\Models\OportunidadeTarefa::statusOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('taskForm.status')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field crm-field-full">
                        <span>Título</span>
                        <input type="text" wire:model.defer="taskForm.titulo">
                        @error('taskForm.titulo')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field">
                        <span>Data prevista</span>
                        <input type="date" wire:model.defer="taskForm.data_prevista">
                        @error('taskForm.data_prevista')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>
                </div>

                <div class="crm-form-actions">
                    <button type="submit" class="crm-btn crm-btn-primary">
                        {{ filled($taskForm['id']) ? 'Salvar tarefa' : 'Adicionar tarefa' }}
                    </button>

                    @if (filled($taskForm['id']))
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="resetTaskForm">
                            Cancelar edição
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>
@endif
