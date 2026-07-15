"""add bom link fields to pcf_records

Revision ID: 003
Revises: 002
Create Date: 2026-06-08
"""
from alembic import op
import sqlalchemy as sa

revision = '003'
down_revision = '002'
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column('pcf_records', sa.Column('pcf_request_line_id', sa.String(36), nullable=True))
    op.add_column('pcf_records', sa.Column('bom_line_id', sa.String(36), nullable=True))
    op.add_column('pcf_records', sa.Column('quantity_unit', sa.String(20), nullable=True))

    # 將舊的 data_quality 欄位從 String 改為 Enum（需先刪後建）
    op.execute("ALTER TABLE pcf_records ALTER COLUMN data_quality TYPE VARCHAR(20)")
    op.execute("UPDATE pcf_records SET data_quality = 'estimated' WHERE data_quality NOT IN ('primary', 'secondary', 'estimated')")

    op.create_index('ix_pcf_records_bom_line_id', 'pcf_records', ['bom_line_id'])
    op.create_index('ix_pcf_records_pcf_request_line_id', 'pcf_records', ['pcf_request_line_id'])


def downgrade() -> None:
    op.drop_index('ix_pcf_records_pcf_request_line_id', 'pcf_records')
    op.drop_index('ix_pcf_records_bom_line_id', 'pcf_records')
    op.drop_column('pcf_records', 'quantity_unit')
    op.drop_column('pcf_records', 'bom_line_id')
    op.drop_column('pcf_records', 'pcf_request_line_id')
