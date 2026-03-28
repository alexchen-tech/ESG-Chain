import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common'
import { CAPService } from './cap.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('cap')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('cap')
export class CAPController {
  constructor(private capService: CAPService) {}

  @Get()
  list(@Query() query: any) {
    return this.capService.list({
      ...query,
      page: query.page ? Number(query.page) : 1,
      limit: query.limit ? Number(query.limit) : 15,
    })
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.capService.findOne(id)
  }

  @Post()
  create(@Body() body: any) {
    return this.capService.create(body)
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.capService.update(id, body)
  }

  @Post(':id/close')
  close(@Param('id') id: string) {
    return this.capService.close(id)
  }
}
