import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class ReportsService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    type?: string
    status?: string
    page?: number
    limit?: number
  }) {
    const { search, type, status, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { title: { contains: search, mode: 'insensitive' } },
        { period: { contains: search, mode: 'insensitive' } },
      ]
    }
    if (type) where.type = type
    if (status) where.status = status

    const [data, total] = await Promise.all([
      this.prisma.report.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: { createdBy: { select: { name: true } } },
      }),
      this.prisma.report.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const report = await this.prisma.report.findUnique({
      where: { id },
      include: { createdBy: { select: { name: true, email: true } } },
    })
    if (!report) throw new NotFoundException('報告不存在')
    return report
  }

  async create(dto: any, userId: string) {
    return this.prisma.report.create({
      data: {
        ...dto,
        createdById: userId,
      },
    })
  }

  async update(id: string, dto: any) {
    const report = await this.prisma.report.findUnique({ where: { id } })
    if (!report) throw new NotFoundException('報告不存在')
    const data: any = { ...dto }
    if (dto.status === 'final' && !report.publishedAt) {
      data.publishedAt = new Date()
    }
    return this.prisma.report.update({ where: { id }, data })
  }
}
