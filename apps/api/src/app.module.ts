import { Module } from '@nestjs/common'
import { PrismaModule } from './prisma/prisma.module'
import { AuthModule } from './modules/auth/auth.module'
import { UsersModule } from './modules/users/users.module'
import { SuppliersModule } from './modules/suppliers/suppliers.module'
import { SAQModule } from './modules/saq/saq.module'
import { CAPModule } from './modules/cap/cap.module'
import { TradeGoodsModule } from './modules/trade-goods/trade-goods.module'
import { PCFModule } from './modules/pcf/pcf.module'
import { DecarbModule } from './modules/decarb/decarb.module'
import { ReportsModule } from './modules/reports/reports.module'
import { DashboardModule } from './modules/dashboard/dashboard.module'
import { NotificationsModule } from './modules/notifications/notifications.module'

@Module({
  imports: [
    PrismaModule,
    AuthModule,
    UsersModule,
    SuppliersModule,
    SAQModule,
    CAPModule,
    TradeGoodsModule,
    PCFModule,
    DecarbModule,
    ReportsModule,
    DashboardModule,
    NotificationsModule,
  ],
})
export class AppModule {}
