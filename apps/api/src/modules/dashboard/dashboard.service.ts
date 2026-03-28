import { Injectable } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class DashboardService {
  constructor(private prisma: PrismaService) {}

  async getStats() {
    const [
      totalSuppliers,
      activeSuppliers,
      highRiskSuppliers,
      totalSAQs,
      completedSAQs,
      openCAPCount,
      cbamExposureCount,
      carbonSuppliers,
    ] = await Promise.all([
      this.prisma.supplier.count(),
      this.prisma.supplier.count({ where: { status: 'active' } }),
      this.prisma.supplier.count({ where: { riskLevel: { in: ['high', 'critical'] } } }),
      this.prisma.sAQ.count({ where: { status: { not: 'draft' } } }),
      this.prisma.sAQ.count({ where: { status: { in: ['submitted', 'reviewed', 'closed'] } } }),
      this.prisma.cAP.count({ where: { status: { in: ['open', 'in_progress', 'overdue'] } } }),
      this.prisma.tradeGood.count({ where: { cbamApplicable: true } }),
      this.prisma.supplier.findMany({
        where: { carbonIntensity: { not: null } },
        select: { carbonIntensity: true },
      }),
    ])

    const saqCompletionRate = totalSAQs > 0 ? Math.round((completedSAQs / totalSAQs) * 100) : 0

    const avgCarbonIntensity =
      carbonSuppliers.length > 0
        ? carbonSuppliers.reduce((sum, s) => sum + (s.carbonIntensity ?? 0), 0) / carbonSuppliers.length
        : 0

    return {
      totalSuppliers,
      activeSuppliers,
      highRiskSuppliers,
      saqCompletionRate,
      openCAPCount,
      avgCarbonIntensity: Math.round(avgCarbonIntensity * 100) / 100,
      cbamExposureCount,
    }
  }

  async getRiskDistribution() {
    const [low, medium, high, critical] = await Promise.all([
      this.prisma.supplier.count({ where: { riskLevel: 'low' } }),
      this.prisma.supplier.count({ where: { riskLevel: 'medium' } }),
      this.prisma.supplier.count({ where: { riskLevel: 'high' } }),
      this.prisma.supplier.count({ where: { riskLevel: 'critical' } }),
    ])

    return { low, medium, high, critical }
  }

  async getRecentActivity() {
    const [recentSAQs, recentCAPs] = await Promise.all([
      this.prisma.sAQ.findMany({
        take: 5,
        orderBy: { updatedAt: 'desc' },
        include: { supplier: { select: { name: true } } },
      }),
      this.prisma.cAP.findMany({
        take: 5,
        orderBy: { updatedAt: 'desc' },
        include: { supplier: { select: { name: true } } },
      }),
    ])

    return { recentSAQs, recentCAPs }
  }
}
