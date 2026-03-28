import { Injectable, UnauthorizedException } from '@nestjs/common'
import { JwtService } from '@nestjs/jwt'
import { PrismaService } from '../../prisma/prisma.service'
import * as bcrypt from 'bcrypt'

@Injectable()
export class AuthService {
  constructor(
    private prisma: PrismaService,
    private jwt: JwtService,
  ) {}

  async login(email: string, password: string) {
    const user = await this.prisma.user.findUnique({ where: { email } })
    if (!user) throw new UnauthorizedException('帳號或密碼錯誤')

    const valid = await bcrypt.compare(password, user.password)
    if (!valid) throw new UnauthorizedException('帳號或密碼錯誤')
    if (user.status !== 'active') throw new UnauthorizedException('帳號已停用')

    const payload = { sub: user.id, email: user.email, role: user.role }
    const accessToken = this.jwt.sign(payload)

    const { password: _, ...userWithoutPassword } = user
    return { accessToken, user: userWithoutPassword }
  }

  async me(userId: string) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      select: { id: true, email: true, name: true, role: true, status: true, avatarUrl: true, supplierId: true },
    })
    if (!user) throw new UnauthorizedException()
    return user
  }
}
