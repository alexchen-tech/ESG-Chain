"""add sasb_industry_code to scoring_models

Revision ID: 002
Revises: 001
Create Date: 2026-05-23
"""
from alembic import op
import sqlalchemy as sa

revision = '002'
down_revision = '001'
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column(
        'scoring_models',
        sa.Column('sasb_industry_code', sa.String(20), nullable=True, comment='SASB Industry code, e.g. EM-IS')
    )
    # Partial unique index: only one active model per industry code
    op.create_index(
        'ix_scoring_models_industry_active',
        'scoring_models',
        ['sasb_industry_code'],
        unique=False,
    )


def downgrade() -> None:
    op.drop_index('ix_scoring_models_industry_active', table_name='scoring_models')
    op.drop_column('scoring_models', 'sasb_industry_code')
