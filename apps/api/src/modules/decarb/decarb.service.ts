import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class DecarbService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    status?: string
    supplierId?: string
    sbtiAligned?: boolean | string
    page?: number
    limit?: number
  }) {
    const { search, status, supplierId, sbtiAligned, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { supplier: { name: { contains: search, mode: 'insensitive' } } },
      ]
    }
    if (status) where.status = status
    if (supplierId) where.supplierId = supplierId
    if (sbtiAligned !== undefined && sbtiAligned !== '') {
      where.sbtiAligned = sbtiAligned === true || sbtiAligned === 'true'
    }

    const [data, total] = await Promise.all([
      this.prisma.decarbPlan.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: {
          supplier: { select: { name: true } },
          milestones: { orderBy: { year: 'asc' } },
        },
      }),
      this.prisma.decarbPlan.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const plan = await this.prisma.decarbPlan.findUnique({
      where: { id },
      include: {
        supplier: true,
        milestones: { orderBy: { year: 'asc' } },
      },
    })
    if (!plan) throw new NotFoundException('減碳計畫不存在')
    return plan
  }

  async create(dto: any) {
    const { milestones, ...planData } = dto
    const plan = await this.prisma.decarbPlan.create({
      data: planData,
    })

    if (milestones && milestones.length > 0) {
      await this.prisma.decarbMilestone.createMany({
        data: milestones.map((m: any) => ({ ...m, planId: plan.id })),
      })
    }

    return this.prisma.decarbPlan.findUnique({
      where: { id: plan.id },
      include: { milestones: true },
    })
  }

  async update(id: string, dto: any) {
    const plan = await this.prisma.decarbPlan.findUnique({ where: { id } })
    if (!plan) throw new NotFoundException('減碳計畫不存在')
    const { milestones, ...planData } = dto
    return this.prisma.decarbPlan.update({ where: { id }, data: planData })
  }
}
