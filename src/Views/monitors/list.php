<div class="content">
    <div class="level">
        <div class="level-left">
            <div class="level-item">
                <h1 class="title">Monitors</h1>
            </div>
        </div>
        <div class="level-right">
            <div class="level-item">
                <a href="/monitors/add" class="button">
                    <span class="icon">
                        <i class="fas fa-plus"></i>
                    </span>
                    <span>Add Monitor</span>
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($monitors)): ?>
        <div class="box has-text-centered">
            <p class="has-text-grey">No monitors found. Create your first monitor to start tracking!</p>
            <a href="/monitors/add" class="button is-primary is-outlined mt-4">Create Monitor</a>
        </div>
    <?php else: ?>
        <div class="columns is-multiline">
            <?php foreach ($monitors as $monitor): ?>
                <div class="column is-one-third">
                    <div class="box">
                        <div class="level is-mobile mb-2">
                            <div class="level-left">
                                <div class="level-item">
                                    <span class="icon-text">
                                        <span class="icon <?= $monitor['current_status'] ? 'has-text-success' : 'has-text-danger' ?>">
                                            <i class="fas <?= $monitor['current_status'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                                        </span>
                                        <span class="has-text-weight-bold"><?= html_entity_decode(htmlspecialchars($monitor['name'])) ?></span>
                                    </span>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <div class="dropdown is-right is-hoverable">
                                        <div class="dropdown-trigger">
                                            <button class="button is-small is-ghost" aria-label="more options">
                                                <span class="icon">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </span>
                                            </button>
                                        </div>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-content">
                                                <a href="/monitors/<?= $monitor['id'] ?>/edit" class="dropdown-item">
                                                    <span class="icon-text">
                                                        <span class="icon"><i class="fas fa-edit"></i></span>
                                                        <span>Edit</span>
                                                    </span>
                                                </a>
                                                <hr class="dropdown-divider">
                                                <a href="#" class="dropdown-item has-text-danger" 
                                                   onclick="confirmDelete(<?= $monitor['id'] ?>)">
                                                    <span class="icon-text">
                                                        <span class="icon"><i class="fas fa-trash"></i></span>
                                                        <span>Delete</span>
                                                    </span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="has-text-grey is-size-7 mb-3">
                            <span class="icon-text">
                                <span class="icon">
                                    <i class="fas <?= $monitor['type'] === 'http' ? 'fa-globe' : 'fa-network-wired' ?>"></i>
                                </span>
                                <a href="<?= $monitor['type'] === 'http' ? htmlspecialchars($monitor['url']) : '' ?>" target="_blank"><span><?= htmlspecialchars($monitor['url']) ?></span></a>
                            </span>
                        </p>

                        <p class="has-text-grey is-size-7">
                            <span class="icon-text">
                                <span class="icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <span>Created <?= (new DateTime($monitor['created_at']))->format('F j, Y') ?></span>
                            </span>
                        </p>

                        <div class="level is-mobile">
                            <div class="level-left">
                                <div class="level-item">
                                    <span class="tag is-dark">
                                        <?= strtoupper($monitor['type']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <?php if ($monitor['last_response_time']): ?>
                                        <span class="tag is-light">
                                            <?= $monitor['last_response_time'] ?>ms
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Confirm Delete</p>
            <button class="delete" aria-label="close" onclick="closeDeleteModal()"></button>
        </header>
        <section class="modal-card-body">
            Are you sure you want to delete this monitor? This action cannot be undone.
        </section>
        <footer class="modal-card-foot">
            <form id="deleteForm" method="POST">
                <button class="button is-danger">Delete</button>
            </form>
            <button class="button" onclick="closeDeleteModal()">Cancel</button>
        </footer>
    </div>
</div>

<script>
function confirmDelete(id) {
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    form.action = `/monitors/${id}/delete`;
    modal.classList.add('is-active');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('is-active');
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteModal');
    if (e.target.classList.contains('modal-background')) {
        modal.classList.remove('is-active');
    }
});
</script>