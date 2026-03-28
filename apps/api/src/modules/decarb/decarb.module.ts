import { Module } from '@nestjs/common'
import { DecarbController } from './decarb.controller'
import { DecarbService } from './decarb.service'

@Module({ controllers: [DecarbController], providers: [DecarbService] })
export class DecarbModule {}
