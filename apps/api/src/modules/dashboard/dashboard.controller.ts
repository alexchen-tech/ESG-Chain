import { Controller, Get, UseGuards } from '@nestjs/common'
import { DashboardService } from './dashboard.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('dashboard')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('dashboard')
export class DashboardController {
  constructor(private dashboardService: DashboardService) {}

  @Get('stats')
  getStats() {
    return this.dashboardService.getStats().then(data => ({ data }))
  }

  @Get('risk-distribution')
  getRiskDistribution() {
    return this.dashboardService.getRiskDistribution().then(data => ({ data }))
  }

  @Get('recent-activity')
  getRecentActivity() {
    return this.dashboardService.getRecentActivity().then(data => ({ data }))
  }
}
