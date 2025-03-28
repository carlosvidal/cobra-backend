<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Factura<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Editar Factura</h1>
    </div>
</div>

<?php if (session()->has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= session('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('invoices/update/' . $invoice['uuid']) ?>" method="post">
            <?= csrf_field() ?>
            
            <div class="mb-3">
                <label for="client_id" class="form-label">Cliente *</label>
                <select name="client_id" id="client_id" class="form-select" required>
                    <option value="">Seleccione un cliente</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>" <?= old('client_id', $invoice['client_id']) == $client['id'] ? 'selected' : '' ?>>
                            <?= esc($client['business_name']) ?> (<?= esc($client['document_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (session('validation') && session('validation')->hasError('client_id')): ?>
                    <div class="invalid-feedback d-block">
                        <?= session('validation')->getError('client_id') ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="invoice_number" class="form-label">Número de Factura *</label>
                <input type="text" class="form-control <?= session('validation') && session('validation')->hasError('invoice_number') ? 'is-invalid' : '' ?>" 
                       id="invoice_number" name="invoice_number" 
                       value="<?= old('invoice_number', $invoice['invoice_number']) ?>" required maxlength="50">
                <?php if (session('validation') && session('validation')->hasError('invoice_number')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('invoice_number') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="concept" class="form-label">Concepto *</label>
                <input type="text" class="form-control <?= session('validation') && session('validation')->hasError('concept') ? 'is-invalid' : '' ?>" 
                       id="concept" name="concept" 
                       value="<?= old('concept', $invoice['concept']) ?>" required maxlength="255">
                <?php if (session('validation') && session('validation')->hasError('concept')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('concept') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Importe *</label>
                <input type="number" class="form-control <?= session('validation') && session('validation')->hasError('amount') ? 'is-invalid' : '' ?>" 
                       id="amount" name="amount" step="0.01" 
                       value="<?= old('amount', $invoice['amount']) ?>" required>
                <?php if (session('validation') && session('validation')->hasError('amount')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('amount') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="currency" class="form-label">Moneda *</label>
                <select name="currency" id="currency" class="form-select <?= session('validation') && session('validation')->hasError('currency') ? 'is-invalid' : '' ?>" required>
                    <option value="PEN" <?= old('currency', $invoice['currency']) === 'PEN' ? 'selected' : '' ?>>PEN - Soles</option>
                    <option value="USD" <?= old('currency', $invoice['currency']) === 'USD' ? 'selected' : '' ?>>USD - Dólares</option>
                </select>
                <?php if (session('validation') && session('validation')->hasError('currency')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('currency') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Estado *</label>
                <select name="status" id="status" class="form-select <?= session('validation') && session('validation')->hasError('status') ? 'is-invalid' : '' ?>" required>
                    <option value="pending" <?= old('status', $invoice['status']) === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="paid" <?= old('status', $invoice['status']) === 'paid' ? 'selected' : '' ?>>Pagada</option>
                    <option value="cancelled" <?= old('status', $invoice['status']) === 'cancelled' ? 'selected' : '' ?>>Anulada</option>
                    <option value="rejected" <?= old('status', $invoice['status']) === 'rejected' ? 'selected' : '' ?>>Rechazada</option>
                    <option value="expired" <?= old('status', $invoice['status']) === 'expired' ? 'selected' : '' ?>>Vencida</option>
                </select>
                <?php if (session('validation') && session('validation')->hasError('status')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('status') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="due_date" class="form-label">Fecha de Vencimiento *</label>
                <input type="date" class="form-control <?= session('validation') && session('validation')->hasError('due_date') ? 'is-invalid' : '' ?>" 
                       id="due_date" name="due_date" 
                       value="<?= old('due_date', $invoice['due_date']) ?>" required>
                <?php if (session('validation') && session('validation')->hasError('due_date')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('due_date') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="external_id" class="form-label">ID Externo</label>
                <input type="text" class="form-control <?= session('validation') && session('validation')->hasError('external_id') ? 'is-invalid' : '' ?>" 
                       id="external_id" name="external_id" 
                       value="<?= old('external_id', $invoice['external_id']) ?>" maxlength="36">
                <?php if (session('validation') && session('validation')->hasError('external_id')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('external_id') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notas</label>
                <textarea class="form-control <?= session('validation') && session('validation')->hasError('notes') ? 'is-invalid' : '' ?>" 
                          id="notes" name="notes" rows="3"><?= old('notes', $invoice['notes']) ?></textarea>
                <?php if (session('validation') && session('validation')->hasError('notes')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('notes') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>