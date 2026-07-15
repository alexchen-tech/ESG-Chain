"""initial schema

Revision ID: 001
Revises:
Create Date: 2024-01-01 00:00:00.000000
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID

revision = '001'
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.create_table(
        'emission_factors',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('source', sa.String(50), nullable=False),
        sa.Column('category', sa.String(100), nullable=False),
        sa.Column('subcategory', sa.String(100)),
        sa.Column('activity', sa.String(200), nullable=False),
        sa.Column('unit', sa.String(50), nullable=False),
        sa.Column('co2_factor', sa.Float, nullable=False),
        sa.Column('ch4_factor', sa.Float),
        sa.Column('n2o_factor', sa.Float),
        sa.Column('gwp_co2', sa.Float, default=1.0),
        sa.Column('gwp_ch4', sa.Float, default=28.0),
        sa.Column('gwp_n2o', sa.Float, default=265.0),
        sa.Column('country', sa.String(2)),
        sa.Column('year', sa.Integer),
        sa.Column('description', sa.Text),
        sa.Column('created_at', sa.DateTime(timezone=True)),
        sa.Column('updated_at', sa.DateTime(timezone=True)),
    )
    op.create_index('idx_ef_category_country', 'emission_factors', ['category', 'country'])
    op.create_index('idx_ef_source', 'emission_factors', ['source'])

    op.create_table(
        'scoring_models',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('name', sa.String(100), nullable=False),
        sa.Column('version', sa.String(20)),
        sa.Column('is_active', sa.Boolean, default=True),
        sa.Column('weight_e', sa.Float, nullable=False),
        sa.Column('weight_s', sa.Float, nullable=False),
        sa.Column('weight_g', sa.Float, nullable=False),
        sa.Column('grade_a_threshold', sa.Float, default=80.0),
        sa.Column('grade_b_threshold', sa.Float, default=60.0),
        sa.Column('grade_c_threshold', sa.Float, default=40.0),
        sa.Column('grade_d_threshold', sa.Float, default=20.0),
        sa.Column('description', sa.Text),
        sa.Column('created_at', sa.DateTime(timezone=True)),
    )

    op.create_table(
        'pcf_records',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('supplier_id', sa.String(36), nullable=False, index=True),
        sa.Column('reference_year', sa.Integer, nullable=False),
        sa.Column('scope', sa.Integer, nullable=False),
        sa.Column('category', sa.String(100)),
        sa.Column('activity_description', sa.Text),
        sa.Column('quantity', sa.Float, nullable=False),
        sa.Column('unit', sa.String(50), nullable=False),
        sa.Column('emission_factor_id', UUID(as_uuid=True)),
        sa.Column('emission_factor_value', sa.Float),
        sa.Column('total_kg_co2e', sa.Float, nullable=False),
        sa.Column('data_quality', sa.String(20), default='estimated'),
        sa.Column('notes', sa.Text),
        sa.Column('celery_job_id', sa.String(100)),
        sa.Column('created_at', sa.DateTime(timezone=True)),
        sa.Column('updated_at', sa.DateTime(timezone=True)),
    )
    op.create_index('idx_pcf_supplier_year', 'pcf_records', ['supplier_id', 'reference_year'])

    op.create_table(
        'risk_assessments',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('supplier_id', sa.String(36), nullable=False, index=True),
        sa.Column('assessment_year', sa.Integer, nullable=False),
        sa.Column('score_environment', sa.Float),
        sa.Column('score_social', sa.Float),
        sa.Column('score_governance', sa.Float),
        sa.Column('score_country', sa.Float),
        sa.Column('score_supply_chain', sa.Float),
        sa.Column('total_score', sa.Float),
        sa.Column('risk_level', sa.String(20)),
        sa.Column('celery_job_id', sa.String(100)),
        sa.Column('notes', sa.Text),
        sa.Column('created_at', sa.DateTime(timezone=True)),
    )

    op.create_table(
        'risk_factors',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('assessment_id', UUID(as_uuid=True), nullable=False, index=True),
        sa.Column('dimension', sa.String(50), nullable=False),
        sa.Column('factor_name', sa.String(100), nullable=False),
        sa.Column('score', sa.Float, nullable=False),
        sa.Column('weight', sa.Float, default=1.0),
        sa.Column('evidence', sa.Text),
        sa.Column('created_at', sa.DateTime(timezone=True)),
    )


def downgrade() -> None:
    op.drop_table('risk_factors')
    op.drop_table('risk_assessments')
    op.drop_table('pcf_records')
    op.drop_table('scoring_models')
    op.drop_table('emission_factors')
