import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'
import * as bcrypt from 'bcrypt'

@Injectable()
export class UsersService {
  constructor(private prisma: PrismaService) {}

  async list(query: { page?: number; limit?: number; search?: string } = {}) {
    const { page = 1, limit = 20, search } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { email: { contains: search, mode: 'insensitive' } },
      ]
    }

    const [data, total] = await Promise.all([
      this.prisma.user.findMany({
        where,
        skip,
        take: limit,
        select: { id: true, email: true, name: true, role: true, status: true, avatarUrl: true, createdAt: true },
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.user.count({ where }),
    ])

    return { data, total }
  }

  async findOne(id: string) {
    const user = await this.prisma.user.findUnique({
      where: { id },
      select: { id: true, email: true, name: true, role: true, status: true, avatarUrl: true, supplierId: true, createdAt: true },
    })
    if (!user) throw new NotFoundException('使用者不存在')
    return user
  }

  async create(dto: { email: string; name: string; password: string; role?: string }) {
    const hashed = await bcrypt.hash(dto.password, 12)
    return this.prisma.user.create({
      data: { ...dto, password: hashed },
      select: { id: true, email: true, name: true, role: true, status: true, createdAt: true },
    })
  }

  async update(id: string, dto: any) {
    const user = await this.prisma.user.findUnique({ where: { id } })
    if (!user) throw new NotFoundException('使用者不存在')

    const data: any = { ...dto }
    if (dto.password) {
      data.password = await bcrypt.hash(dto.password, 12)
    }

    return this.prisma.user.update({
      where: { id },
      data,
      select: { id: true, email: true, name: true, role: true, status: true, updatedAt: true },
    })
  }
}
