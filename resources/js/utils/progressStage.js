// Daily work closure is independent of an explicitly confirmed project stage.
export function isStageConfirmed(cell, type = cell?.cell_type) {
    if (!cell) return false;
    if (cell.completed_at) return true;
    if (type === 'proof_user' || type === 'proof_v2') {
        return !!(cell.assignment_proof_completed || cell.proof_assignment_completed);
    }
    return false;
}
